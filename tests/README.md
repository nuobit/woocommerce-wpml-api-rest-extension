# Term-language integration regression

Run against a local WordPress installation with WooCommerce, WPML and this
plugin active. Use synthetic variable products translated into each test
language, a translated product category and a translated global attribute.
Assign the corresponding terms to every translated parent. Persistent object
caching must be disabled for this read-only checker.

Supply a local JSON manifest. Keep real installation details and fixture IDs
outside the repository; the following values are illustrative:

```json
{
  "site_url": "https://shop.test",
  "database": "wordpress_test",
  "sku_prefix": "WCWPML-TEST-",
  "attribute_taxonomy": "pa_test_size",
  "product_type_term_id": 4,
  "variable_products": {"en": 101, "es": 102},
  "terms": {
    "en": {"product_cat": [201], "pa_test_size": [301, 302]},
    "es": {"product_cat": [202], "pa_test_size": [303, 304]}
  }
}
```

The prefix must also start each fixture product title. Supply only the synthetic
category and attribute terms, excluding automatically assigned default
categories. Use actual IDs from the local fixture; record expectations before
running the checker.

From this repository, run each mode in a separate process:

```sh
wp --path=/path/to/wordpress --context=cli --skip-packages \
  eval-file tests/term-language.php /path/to/fixture.json rest
wp --path=/path/to/wordpress --context=cli --skip-packages \
  eval-file tests/term-language.php /path/to/fixture.json non-rest
wp --path=/path/to/wordpress --context=cli --skip-packages \
  eval-file tests/term-language.php /path/to/fixture.json rest-false
```

Exit status is nonzero when an assertion fails. The JSON result lists failed
assertion names without database contents. Query-contract checks execute the
plugin's actual filter against real term rows; input-language and translation
flags are temporarily controlled in that process to cover its no-op branches.
The final checks prime relationships through WordPress's normal mixed query and
assert that WooCommerce still constructs a variable product. No database rows
are changed and only process-local object cache entries are cleared.

These checks complement real HTTP product/variation writes and storefront
sorting assertions. They do not replace an end-to-end connector verification.
