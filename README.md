# WooCommerce Product Importer

A small admin plugin to copy top-level product categories and review/import products from another WooCommerce store one at a time.

## Install

1. Download this repository as ZIP (or download `woocommerce-product-importer.php`).
2. Create a folder named `woocommerce-product-importer` inside `wp-content/plugins/`.
3. Put `woocommerce-product-importer.php` inside that folder.
4. Activate **WooCommerce Product Importer** from WordPress Plugins.
5. Open **WooCommerce → واردکننده محصولات**.

## Use

1. Enter the source store URL.
2. Click **دریافت دسته‌بندی محصولات**.
3. Review the top-level categories and click **تأیید و افزودن دسته‌بندی‌ها**.
4. Review each product and choose:
   - **افزودن محصول**: creates a draft product and advances.
   - **محصول بعدی**: skips the current product and advances.
   - **گروه بعدی**: skips the rest of the current category.
5. The current category and product position are saved in the WordPress database.

## Requirements and current limitations

- WordPress with WooCommerce active.
- Source store must expose the public WooCommerce Store API at `/wp-json/wc/store/v1/`.
- Only top-level product categories are imported; child categories are ignored.
- Products are created as **drafts**.
- Imports the first product image only, and only when the image is hosted on the same domain as the source store.
- Standard product name, descriptions, SKU (when available), regular price, and category are copied.
- Variable products are currently imported as simple draft products; variations, galleries, attributes, stock, shipping data, and currency conversion are not handled.
- Source prices are copied as displayed numeric amounts. Make sure the source and destination currencies/units match before importing.
- Existing products created by this importer are detected to avoid duplicate imports.
- The **شروع از ابتدا** button resets the import cursor and category state; it does not delete imported products or destination categories.

## Important

Use this plugin only with stores you own or have permission to copy from. Check product descriptions, images, trademarks, and other content rights before publishing imported products.
