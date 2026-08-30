<?php

$limit   = 500;
$dry_run = false;

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
    }
}

$p = Pera_ML_Plugin::instance();

$health = new Pera_ML_Translation_Health(
    $p->status(),
    $p->storage(),
    $p->ui()
);

$orchestrator = new Pera_ML_Translation_Health_Orchestrator(
    $p->status(),
    $p->storage(),
    $p->translator(),
    $p->ui(),
    $p->ui_registry()
);

$inventory = $health->inventory();
$rows      = isset( $inventory['rows'] ) && is_array( $inventory['rows'] )
    ? $inventory['rows']
    : array();

$pending = array();

foreach ( $rows as $row ) {
    if (
        ! isset( $row['status'] ) ||
        ! in_array( $row['status'], array( 'missing', 'stale' ), true )
    ) {
        continue;
    }

    $pending[] = $row;
}

if ( ! $pending ) {
    echo "No incomplete translations found." . PHP_EOL;
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
    echo 'Dry run: ' . $shown . ' row(s) shown | Limit: ' . $limit . PHP_EOL;
} else {
    echo 'Completed: ' . $success
        . ' | Errors: ' . $errors
        . ' | Skipped: ' . $skipped
        . PHP_EOL;
}
