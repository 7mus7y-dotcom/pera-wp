#!/usr/bin/env bash
# Run on an OFFLINE BUILD MACHINE, never the cPanel production host.
set -euo pipefail
mkdir -p work/nearby-poi
curl --fail --location --retry 3 --proto '=https' \
  https://download.bbbike.org/osm/bbbike/Istanbul/Istanbul.osm.pbf \
  -o work/nearby-poi/Istanbul.osm.pbf.part
osmium fileinfo -e work/nearby-poi/Istanbul.osm.pbf.part -F pbf > work/nearby-poi/source-info.txt
mv work/nearby-poi/Istanbul.osm.pbf.part work/nearby-poi/Istanbul.osm.pbf
sha256sum work/nearby-poi/Istanbul.osm.pbf > work/nearby-poi/source.sha256
# Review the source-info bounds before building. The canned city extract may not
# cover the full metropolitan rectangle; use a reviewed custom BBBike extract or
# crop Geofabrik Turkey OFFLINE if it falls short. See docs/nearby-amenities.md.
