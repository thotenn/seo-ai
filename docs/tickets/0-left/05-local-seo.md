# 05 — Local SEO / Multi-Location

**Priority:** MEDIUM
**Effort:** XL (10+ files, 2+ weeks)
**Rank Math module:** `local-seo` (Pro, requires Schema)

## What Rank Math Has

### Multi-Location Management
- Custom Post Type: `rank_math_locations`
- Location taxonomy for grouping
- Per-location fields: name, address, phone, business hours, geo coordinates

### LocalBusiness Schema
- LocalBusiness JSON-LD per location
- Address schema (PostalAddress)
- Opening hours (OpeningHoursSpecification)
- Contact details
- Geo coordinates (latitude/longitude)

### Location Display
- `[rank-math-location]` shortcode
- Gutenberg block for locations
- Interactive map display (roadmap style)
- Location search/filter functionality
- Configurable location limit

### KML File Export
- Export location data as KML
- Google Maps / Google Earth compatibility

### Settings
- Map style configuration (roadmap, satellite, etc.)
- Location limit for display
- Map preview in admin

## What SEO AI Currently Has

- Organization/Person schema with basic address, phone, email
- No multi-location support
- No LocalBusiness schema variants
- No location CPT or shortcodes

## Implementation Plan

### Phase 1 — Single Location Enhancement
1. Enhance existing Organization schema with LocalBusiness variants
2. Add opening hours fields
3. Add geo coordinates (lat/lng)
4. LocalBusiness sub-types (Restaurant, Store, MedicalBusiness, etc.)

### Phase 2 — Multi-Location
1. Register `seo_ai_location` CPT
2. Location metabox with address, hours, contact, coordinates
3. Location taxonomy for grouping
4. Per-location LocalBusiness schema output

### Phase 3 — Frontend Display
1. `[seo_ai_location]` shortcode
2. Gutenberg block
3. Location list/grid display
4. Map integration (Google Maps or OpenStreetMap)

### Phase 4 — Advanced
1. KML file export
2. Location search functionality
3. Store locator feature

## Files to Create

- `includes/modules/local-seo/class-local-seo.php` — Module entry point
- `includes/modules/local-seo/class-location-cpt.php` — Custom post type registration
- `includes/modules/local-seo/class-location-schema.php` — LocalBusiness schema generation
- `includes/modules/local-seo/class-location-shortcode.php` — Frontend shortcode
- `includes/admin/views/settings/tab-local.php` — Settings tab

## Notes

- Phase 1 alone (single location enhancement) would serve 80% of users
- Multi-location is mainly needed by franchise/chain businesses
- Map integration requires either Google Maps API key or use free OpenStreetMap
- Consider using AI to auto-generate business descriptions per location
