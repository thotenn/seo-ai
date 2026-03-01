# 06 — WooCommerce Integration

**Priority:** MEDIUM
**Effort:** L (5-10 files, 3-7 days)
**Rank Math module:** `woocommerce` (Free basic + Pro advanced)

## What Rank Math Has

### Product Schema Enhancement
- **ProductGroup Schema** — Product variants with `variesBy` attributes (size, color, etc.)
- **Product Carousel Schema** — ItemList with multiple products for category pages
- **GTIN/ISBN/MPN Fields** — Product identifier fields with migration tool from other plugins

### Product Visibility Control
- **Noindex Hidden Products** — Auto-set noindex on hidden/draft products
- **Exclude from Sitemap** — Remove hidden products from XML sitemap
- **Stock Status Filtering** — Exclude out-of-stock products from sitemap

### WooCommerce-Specific SEO
- **Product-Specific SEO Tests** — Adjusted content analysis for product pages
- **Review Schema** — Customer review/rating integration into Product schema
- **WooCommerce Admin Settings** — Dedicated settings section

### GTIN Migration Tool
- Import GTIN values from other SEO plugins
- Database migration for product identifiers
- Legacy data conversion

## What SEO AI Currently Has

- Basic Product schema (name, description, offers, price, availability, rating)
- No WooCommerce-specific detection or handling
- No product variant support
- No GTIN/ISBN/MPN fields
- No WooCommerce visibility awareness

## Implementation Plan

### Phase 1 — WooCommerce Awareness
1. Detect WooCommerce active status
2. Register SEO AI metabox on product post type
3. Add GTIN/ISBN/MPN fields to product metabox
4. Populate Product schema from WooCommerce product data (price, stock, images, categories)

### Phase 2 — Product Schema Enhancement
1. ProductGroup schema for variable products
2. Product review/rating schema from WooCommerce reviews
3. Product carousel (ItemList) for shop/category archives

### Phase 3 — Visibility & Sitemap
1. Auto-noindex hidden/draft products
2. Exclude out-of-stock products from sitemap (setting)
3. WooCommerce-specific SEO analysis adjustments

## Files to Create

- `includes/modules/woocommerce/class-woocommerce.php` — Module entry point
- `includes/modules/woocommerce/class-product-schema.php` — Enhanced product schema
- `includes/modules/woocommerce/class-product-meta.php` — GTIN/ISBN metabox fields

## Files to Modify

- `includes/modules/schema/class-schema-manager.php` — Product schema enhancements
- `includes/modules/sitemap/class-sitemap-manager.php` — WooCommerce filters

## Notes

- Only activate this module if WooCommerce is active
- WooCommerce has its own structured data output — need to avoid duplicates (remove WC's schema when ours is active)
- Variable products are the key differentiator — ProductGroup schema
- GTIN fields are increasingly important for Google Shopping
