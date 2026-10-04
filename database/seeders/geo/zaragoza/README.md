# Zaragoza geo data

Files the seeders import (SPEC §5, §8). They're built offline and committed;
the app never fetches geo data at runtime.

| file | what | built by |
|---|---|---|
| `neighbourhoods.geojson` | district boundaries, population and derived indices | `php artisan geo:build` |
| `points_of_interest.csv` | typed points of interest with their OSM ids | `php artisan geo:build` |
| `footfall_points.csv` | the footfall surface: commercial street points with their footfall, overall and per day part | `php artisan geo:build` |
| `manifest.json` | when and from what it was built | `php artisan geo:build` |
| `sources/population.csv` | population per district — **fill in by hand** from the municipal padrón or INE | you |
| `sources/pedestrian_counts.csv` | manual pedestrian counts for calibration — **collect by hand** | you |
| `sources/neighbourhoods.geojson` | optional: district boundaries, if OSM doesn't have them | you |

Until `geo:build` has been run, the seeders fall back to the placeholder
files one level up and the game generates footfall from the neighbourhood
indices.

## Rebuilding

On a machine with internet access:

```bash
php artisan geo:fetch       # OSM streets, POIs and district boundaries → storage/app/geo (not committed)
php artisan geo:build       # → the files above
php artisan db:seed         # load them
php artisan geo:calibrate --fit   # once you have pedestrian counts
```

Settings (bounding box, POI types, footfall weights and radii) are in
`config/geo.php`.

## Licence

`neighbourhoods.geojson` (when built from OSM boundaries),
`points_of_interest.csv` and `footfall_points.csv` are derived from
OpenStreetMap data: © OpenStreetMap contributors, available under the
[Open Database License (ODbL)](https://opendatacommons.org/licenses/odbl/).
If you distribute these files or a database built from them, they stay
under the ODbL and must carry this attribution. The map shows the
attribution wherever OSM data appears.

Population figures come from the source named in `sources/population.csv`.
