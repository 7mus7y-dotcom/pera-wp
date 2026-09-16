<?php

$limit       = 500;
$dry_run     = false;
$status      = 'all';
$object_type = null;
$object_id   = null;
$field       = null;

$cli_args = isset( $args ) && is_array( $args )
    ? $args
    : array();

foreach ( $cli_args as $arg ) {
    if ( 'dry-run' === $arg ) {
        $dry_run = true;
        continue;
    }

    if ( 0 === strpos( $arg, 'limit=' ) ) {
        $requested = absint( substr( $arg, 6 ) );

        if ( $requested > 0 ) {
            $limit = min( 5000, $requested );
        }

        continue;
    }

    if ( 0 === strpos( $arg, 'status=' ) ) {
        $requested_status = substr( $arg, 7 );

        if ( ! in_array( $requested_status, array( 'missing', 'stale', 'all' ), true ) ) {
            echo 'ERROR: Invalid status filter "' . $requested_status
                . '". Expected missing, stale, or all.'
                . PHP_EOL;
            return;
        }

        $status = $requested_status;
        continue;
    }

    if ( 0 === strpos( $arg, 'object_type=' ) ) {
        $requested_object_type = substr( $arg, 12 );

        if (
            '' === $requested_object_type ||
            ! preg_match( '/^[A-Za-z0-9_-]+(?::[A-Za-z0-9_-]+)?$/', $requested_object_type )
        ) {
            echo 'ERROR: Invalid object_type filter "' . $requested_object_type . '".' . PHP_EOL;
            return;
        }

        $object_type = $requested_object_type;
        continue;
    }

    if ( 0 === strpos( $arg, 'object_id=' ) ) {
        $requested_object_id = substr( $arg, 10 );

        if (
            '' === $requested_object_id ||
            ! ctype_digit( $requested_object_id ) ||
            (int) $requested_object_id <= 0
        ) {
            echo 'ERROR: Invalid object_id filter "' . $requested_object_id . '".' . PHP_EOL;
            return;
        }

        $object_id = (int) $requested_object_id;
        continue;
    }

    if ( 0 === strpos( $arg, 'field=' ) ) {
        $requested_field = substr( $arg, 6 );

        if (
            '' === $requested_field ||
            ! preg_match( '/^[A-Za-z0-9_-]+(?::[A-Za-z0-9_-]+)?$/', $requested_field )
        ) {
            echo 'ERROR: Invalid field filter "' . $requested_field . '".' . PHP_EOL;
            return;
        }

        $field = $requested_field;
        continue;
    }
}

$filter_summary = '';

if ( null !== $object_type ) {
    $filter_summary .= ' | Object type: ' . $object_type;
}

if ( null !== $object_id ) {
    $filter_summary .= ' | Object ID: ' . $object_id;
}

if ( null !== $field ) {
    $filter_summary .= ' | Field: ' . $field;
}

$p = Pera_ML_Plugin::instance();

$health = new Pera_ML_Translation_Health(
    $p->status(),
    $p->storage(),
    $p->ui(),
    $p->registry()
);

$orchestrator = new Pera_ML_Translation_Health_Orchestrator(
    $p->status(),
    $p->storage(),
    $p->translator(),
    $p->ui(),
    $p->ui_registry(),
    $p->registry()
);

$inventory = $health->inventory();
$rows      = isset( $inventory['rows'] ) && is_array( $inventory['rows'] )
    ? $inventory['rows']
    : array();

$pending = array();

foreach ( $rows as $row ) {
    if (
        ! isset( $row['status'] ) ||
        ! in_array( $row['status'], array( 'missing', 'stale' ), true ) ||
        ( 'all' !== $status && $status !== $row['status'] ) ||
        (
            null !== $object_type &&
            (
                ! isset( $row['object_type'] ) ||
                $object_type !== $row['object_type']
            )
        ) ||
        (
            null !== $object_id &&
            (
                ! isset( $row['object_id'] ) ||
                $object_id !== (int) $row['object_id']
            )
        ) ||
        (
            null !== $field &&
            (
                ! isset( $row['field'] ) ||
                $field !== $row['field']
            )
        )
    ) {
        continue;
    }

    $pending[] = $row;
}

if ( ! $pending ) {
    echo 'No incomplete translations found. Status filter: '
        . $status
        . $filter_summary
        . PHP_EOL;
    return;
}

$success = 0;
$errors  = 0;
$skipped = 0;
$shown   = 0;

foreach ( $pending as $row ) {
    if ( $dry_run ) {
        if ( $shown >= $limit ) {
            break;
        }
    } elseif ( $success >= $limit ) {
        break;
    }

    $number = $dry_run ? $shown + 1 : $success + 1;

    echo '[' . $number . '/' . $limit . '] '
        . strtoupper( isset( $row['language'] ) ? $row['language'] : '' )
        . ' | '
        . ( isset( $row['object_type'] ) ? $row['object_type'] : '' )
        . ' #'
        . ( isset( $row['object_id'] ) ? $row['object_id'] : 0 )
        . ' | '
        . ( isset( $row['field'] ) ? $row['field'] : '' )
        . ' | '
        . $row['status']
        . PHP_EOL;

    if ( $dry_run ) {
        echo "  DRY RUN" . PHP_EOL;
        $shown++;
        continue;
    }

    $result = $orchestrator->translate( $row );

    if ( is_wp_error( $result ) ) {
        echo '  ERROR: ' . $result->get_error_code() . PHP_EOL;
        $errors++;

        if ( $errors >= 10 ) {
            echo "STOPPED: 10 errors" . PHP_EOL;
            break;
        }

        continue;
    }

    echo "  OK" . PHP_EOL;
    $success++;
}

if ( ! $dry_run ) {
    wp_cache_flush();
}

echo PHP_EOL;

if ( $dry_run ) {
    echo 'Dry run: ' . $shown
        . ' row(s) shown | Limit: ' . $limit
        . ' | Status filter: ' . $status
        . $filter_summary
        . PHP_EOL;
} else {
    echo 'Completed: ' . $success
        . ' | Errors: ' . $errors
        . ' | Skipped: ' . $skipped
        . ' | Status filter: ' . $status
        . $filter_summary
        . PHP_EOL;
}
