# Plugin Check Report

**Plugin:** Adoology for WooCommerce
**Generated at:** 2026-11-25 11:33:49


## `adoology-connector.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | WARNING | textdomain_mismatch | The "Text Domain" header in the plugin file does not match the slug. Found "adoology-for-woocommerce", expected "adoology-wp-plugin". | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 0 | 0 | WARNING | trademarked_term | The plugin slug includes a restricted term. Your plugin slug - "adoology-wp-plugin" - contains the restricted term "plugin" which cannot be used at all in your plugin slug. |  |
| 37 | 178 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/Connection.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 97 | 140 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 105 | 130 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 145 | 138 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 148 | 158 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 153 | 189 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 172 | 144 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 235 | 122 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 486 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 486 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 486 | 28 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_results()\n$table assigned unsafely at line 485. |  |
| 487 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT webhook_id, name FROM {$table} WHERE name LIKE %s OR name LIKE %s&quot; |  |
| 511 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 534 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 534 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 536 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 536 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 536 | 24 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_results() |  |
| 537 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT key_id, description FROM {$table} WHERE description = %s OR description LIKE %s OR description LIKE %s&quot; |  |
| 548 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 548 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 561 | 113 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/IncompleteOrders.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 170 | 100 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 173 | 108 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 177 | 90 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 188 | 94 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 191 | 94 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 240 | 108 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 274 | 106 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 302 | 108 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 361 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 361 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 365 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 365 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 367 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 367 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 372 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 372 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 383 | 97 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 390 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 390 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 390 | 28 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_row()\n$table assigned unsafely at line 386. |  |
| 390 | 51 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT id, status, form_stage, customer_data, product_id, variation_id, quantity, value_minor, currency, flow FROM {$table} WHERE checkout_id = %s&quot; |  |
| 466 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 466 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 467 | 114 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 492 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 494 | 110 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 636 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 636 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 636 | 26 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete_table used in $wpdb-&gt;get_var()\n$incomplete_table assigned unsafely at line 635. |  |
| 636 | 49 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete_table} at &quot;SELECT customer_data FROM {$incomplete_table} WHERE checkout_id = %s&quot; |  |
| 690 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 690 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 690 | 31 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_row()\n$table assigned unsafely at line 689. |  |
| 690 | 54 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT id, status, session_id, customer_data FROM {$table} WHERE checkout_id = %s&quot; |  |
| 714 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 714 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 734 | 33 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 734 | 33 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 743 | 29 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 743 | 29 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 747 | 25 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 747 | 25 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 749 | 25 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 749 | 25 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 753 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 753 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 782 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_key | Detected usage of meta_key, possible slow query. |  |
| 783 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_value | Detected usage of meta_value, possible slow query. |  |
| 794 | 97 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 798 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 798 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 799 | 110 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 804 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 804 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 826 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 826 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 826 | 16 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;query()\n$table assigned unsafely at line 824. |  |
| 827 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;UPDATE {$table} SET status = CASE WHEN status = &#039;submitting_recovery&#039; THEN &#039;incomplete&#039; ELSE &#039;started&#039; END, updated_at = %s WHERE status IN (&#039;submitting&#039;,&#039;submitting_recovery&#039;) AND order_id = 0 AND updated_at &lt; %s&quot; |  |
| 834 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 834 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 834 | 28 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_results()\n$table assigned unsafely at line 824. |  |
| 835 | 17 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT id, checkout_id, session_id, flow, product_id, variation_id, quantity, value_minor, currency, form_stage, customer_data FROM {$table} WHERE status = &#039;started&#039; AND last_activity_at &lt; %s LIMIT %d&quot; |  |
| 840 | 28 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 840 | 28 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 857 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 857 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 857 | 28 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_results()\n$table assigned unsafely at line 824. |  |
| 858 | 17 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT id, checkout_id, session_id, status, flow, product_id, variation_id, quantity, value_minor, currency, form_stage, customer_data FROM {$table} WHERE status IN (&#039;started&#039;,&#039;incomplete&#039;) AND expires_at &lt; %s LIMIT %d&quot; |  |
| 863 | 28 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 863 | 28 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 952 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 952 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 952 | 23 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_row()\n$table assigned unsafely at line 951. |  |
| 952 | 46 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT id, status, order_id FROM {$table} WHERE checkout_id = %s&quot; |  |
| 954 | 100 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 960 | 121 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 963 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 963 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 965 | 167 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 978 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 978 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 978 | 16 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;query()\n$table assigned unsafely at line 977. |  |
| 979 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;UPDATE {$table} SET status = CASE WHEN status = &#039;submitting_recovery&#039; THEN &#039;incomplete&#039; ELSE &#039;started&#039; END, updated_at = %s WHERE checkout_id = %s AND status IN (&#039;submitting&#039;,&#039;submitting_recovery&#039;) AND order_id = 0&quot; |  |
| 1065 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1065 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1065 | 23 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete_table used in $wpdb-&gt;get_row()\n$incomplete_table assigned unsafely at line 1064. |  |
| 1066 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete_table} at &quot;SELECT session_id, customer_data FROM {$incomplete_table} WHERE checkout_id = %s&quot; |  |
| 1218 | 74 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 1234 | 72 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 1250 | 44 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 1251 | 1006 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 1275 | 61 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 1279 | 67 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 1308 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1308 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1310 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1310 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1322 | 159 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 1331 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1331 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1331 | 27 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete_table used in $wpdb-&gt;get_var()\n$incomplete_table assigned unsafely at line 1330. |  |
| 1331 | 50 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete_table} at &quot;SELECT customer_data FROM {$incomplete_table} WHERE checkout_id = %s&quot; |  |
| 1553 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1553 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1592 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1592 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1592 | 28 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_results()\n$table assigned unsafely at line 1586. |  |
| 1592 | 40 | WARNING | WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber | Incorrect number of replacements passed to $wpdb-&gt;prepare(). Found 2 replacement parameters, expected 1. |  |
| 1593 | 17 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT id, checkout_id, status, customer_data FROM {$table} WHERE {$condition} ORDER BY id ASC LIMIT %d&quot; |  |
| 1593 | 17 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$condition} at &quot;SELECT id, checkout_id, status, customer_data FROM {$table} WHERE {$condition} ORDER BY id ASC LIMIT %d&quot; |  |
| 1603 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1603 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1619 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1619 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1619 | 24 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_results()\n$table assigned unsafely at line 1618. |  |
| 1620 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT id, event_id, payload FROM {$table} WHERE anonymous_id = %s&quot; |  |
| 1637 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1637 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1690 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1690 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1690 | 28 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete_table used in $wpdb-&gt;get_results()\n$incomplete_table assigned unsafely at line 1688. |  |
| 1691 | 17 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete_table} at &quot;SELECT * FROM {$incomplete_table} WHERE customer_data &lt;&gt; &#039;&#039; AND id &gt; %d ORDER BY id ASC LIMIT %d&quot; |  |
| 1696 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1696 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1696 | 28 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete_table used in $wpdb-&gt;get_results()\n$incomplete_table assigned unsafely at line 1688. |  |
| 1697 | 17 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete_table} at &quot;SELECT * FROM {$incomplete_table} WHERE customer_data &lt;&gt; &#039;&#039; ORDER BY id ASC LIMIT %d OFFSET %d&quot; |  |
| 1702 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1702 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1702 | 28 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete_table used in $wpdb-&gt;get_results()\n$incomplete_table assigned unsafely at line 1688. |  |
| 1702 | 40 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete_table} at &quot;SELECT * FROM {$incomplete_table} WHERE customer_data &lt;&gt; &#039;&#039; ORDER BY id ASC&quot; |  |
| 1730 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 1730 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 1730 | 24 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $events_table used in $wpdb-&gt;get_results()\n$events_table assigned unsafely at line 1729. |  |
| 1731 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$events_table} at &quot;SELECT event_id, payload FROM {$events_table} WHERE anonymous_id = %s ORDER BY id ASC&quot; |  |

## `src/OrderForm.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 96 | 40 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 97 | 73 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 97 | 129 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 101 | 146 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 136 | 101 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 145 | 52 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 146 | 53 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 147 | 53 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 148 | 56 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 150 | 77 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 151 | 52 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 152 | 55 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 153 | 56 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 154 | 56 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 155 | 65 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 157 | 96 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 158 | 91 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 172 | 64 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 180 | 36 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound | Hook names invoked by a theme/plugin should start with the theme/plugin prefix. Found: &quot;woocommerce_add_to_cart_validation&quot;. |  |
| 182 | 63 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 189 | 62 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 195 | 63 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 203 | 66 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 215 | 75 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 268 | 78 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 288 | 71 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 311 | 83 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 327 | 23 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound | Hook names invoked by a theme/plugin should start with the theme/plugin prefix. Found: &quot;woocommerce_checkout_order_created&quot;. |  |
| 346 | 100 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 360 | 75 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 361 | 67 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 384 | 79 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/Crypto.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 46 | 141 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 52 | 98 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 68 | 96 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 78 | 96 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 94 | 140 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 98 | 115 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 105 | 109 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 113 | 109 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 127 | 151 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 154 | 98 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/Settings.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 59 | 28 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 60 | 28 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 67 | 85 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 67 | 130 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 68 | 86 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 68 | 132 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 69 | 80 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 69 | 120 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 70 | 84 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 70 | 137 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 71 | 83 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 71 | 135 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 72 | 86 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 72 | 132 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 73 | 84 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 73 | 128 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 74 | 80 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 74 | 120 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 161 | 144 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 166 | 134 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 189 | 142 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 296 | 111 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 332 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 332 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 332 | 16 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $events_table used in $wpdb-&gt;query()\n$events_table assigned unsafely at line 331. |  |
| 332 | 22 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$events_table} at &quot;UPDATE {$events_table} SET status = &#039;pending&#039;, attempts = 0, available_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE status IN (&#039;failed&#039;,&#039;retrying&#039;)&quot; |  |
| 353 | 46 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 357 | 48 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 360 | 64 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 365 | 67 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 371 | 71 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 377 | 75 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 383 | 74 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 389 | 76 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 395 | 72 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 401 | 49 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 406 | 113 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 410 | 111 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 412 | 261 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 413 | 134 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 417 | 60 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 424 | 61 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 427 | 147 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 431 | 63 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 433 | 218 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 436 | 58 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 455 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 455 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 455 | 26 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $events_table used in $wpdb-&gt;get_results()\n$events_table assigned unsafely at line 453. |  |
| 455 | 38 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$events_table} at &quot;SELECT status, COUNT(*) AS total FROM {$events_table} GROUP BY status&quot; |  |
| 456 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 456 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 456 | 30 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete_table used in $wpdb-&gt;get_results()\n$incomplete_table assigned unsafely at line 454. |  |
| 456 | 42 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete_table} at &quot;SELECT status, COUNT(*) AS total FROM {$incomplete_table} GROUP BY status&quot; |  |
| 460 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |
| 470 | 35 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 473 | 36 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 478 | 30 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 479 | 33 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 480 | 31 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 481 | 37 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 482 | 36 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 483 | 34 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 484 | 30 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 485 | 40 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 488 | 70 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 495 | 163 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 495 | 339 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 507 | 137 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 509 | 65 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 511 | 175 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 514 | 61 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 516 | 49 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 519 | 92 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 519 | 157 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 519 | 224 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 519 | 294 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 519 | 361 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 519 | 429 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 546 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 546 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 546 | 24 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete_table used in $wpdb-&gt;get_results()\n$incomplete_table assigned unsafely at line 545. |  |
| 546 | 36 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete_table} at &quot;SELECT * FROM {$incomplete_table} ORDER BY updated_at DESC LIMIT 100&quot; |  |
| 548 | 69 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 549 | 222 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 550 | 86 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 550 | 151 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 550 | 220 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 550 | 288 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 550 | 354 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 550 | 420 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 550 | 494 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 550 | 560 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 551 | 97 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 554 | 274 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 554 | 1078 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 570 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 570 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 570 | 23 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete_table used in $wpdb-&gt;get_row()\n$incomplete_table assigned unsafely at line 569. |  |
| 570 | 46 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete_table} at &quot;SELECT * FROM {$incomplete_table} WHERE id = %d&quot; |  |
| 572 | 77 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 572 | 192 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 572 | 357 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 580 | 26 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 581 | 24 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 582 | 31 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 583 | 25 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 584 | 24 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 584 | 109 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 585 | 25 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 586 | 25 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 587 | 27 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 588 | 27 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 589 | 25 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 590 | 32 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 591 | 33 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 592 | 27 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 593 | 27 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 596 | 68 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 597 | 119 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 603 | 67 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 606 | 146 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 622 | 13 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |
| 625 | 68 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 627 | 267 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 628 | 53 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 630 | 74 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 636 | 57 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 637 | 166 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 642 | 73 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 647 | 47 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 653 | 52 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 655 | 52 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 656 | 85 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 656 | 151 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 656 | 218 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 656 | 286 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 656 | 353 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 657 | 96 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 671 | 71 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 672 | 174 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 673 | 47 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 675 | 43 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 675 | 189 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 687 | 69 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 688 | 171 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 691 | 108 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 692 | 122 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 693 | 111 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 694 | 104 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 695 | 126 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 696 | 115 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 699 | 155 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 700 | 163 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 701 | 172 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 703 | 195 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 705 | 110 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 706 | 121 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 707 | 136 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 708 | 99 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 709 | 99 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 710 | 101 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 711 | 108 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 727 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 727 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 727 | 24 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $events_table used in $wpdb-&gt;get_results()\n$events_table assigned unsafely at line 726. |  |
| 727 | 36 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$events_table} at &quot;SELECT event_id, event_name, status, attempts, last_error, created_at, sent_at FROM {$events_table} ORDER BY id DESC LIMIT 100&quot; |  |
| 729 | 65 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 730 | 285 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 731 | 85 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 731 | 152 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 731 | 221 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 731 | 287 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 731 | 355 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 731 | 420 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 732 | 96 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 742 | 81 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 779 | 242 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 796 | 57 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 832 | 67 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 833 | 72 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 834 | 78 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 835 | 71 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 836 | 71 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 837 | 61 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/Plugin.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 75 | 102 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 138 | 51 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 164 | 155 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 178 | 127 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 190 | 119 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/Events.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 57 | 115 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 74 | 95 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 83 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 99 | 106 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 129 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 129 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 129 | 16 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;query()\n$table assigned unsafely at line 127. |  |
| 130 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;UPDATE {$table} SET status = &#039;retrying&#039;, lease_token = NULL, lease_expires_at = NULL, available_at = %s, updated_at = %s WHERE status = &#039;processing&#039; AND (lease_expires_at IS NULL OR lease_expires_at &lt; %s)&quot; |  |
| 135 | 26 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 135 | 26 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 135 | 33 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_col()\n$table assigned unsafely at line 127. |  |
| 136 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT id FROM {$table} WHERE status IN (&#039;pending&#039;,&#039;retrying&#039;) AND available_at &lt;= %s ORDER BY id ASC LIMIT 25&quot; |  |
| 145 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 145 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 145 | 16 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;query()\n$table assigned unsafely at line 127. |  |
| 145 | 22 | WARNING | WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber | Incorrect number of replacements passed to $wpdb-&gt;prepare(). Found 1 replacement parameters, expected 4. |  |
| 147 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;UPDATE {$table} SET status = &#039;processing&#039;, lease_token = %s, lease_expires_at = %s, updated_at = %s WHERE id IN ({$placeholders}) AND status IN (&#039;pending&#039;,&#039;retrying&#039;) AND available_at &lt;= %s&quot; |  |
| 147 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$placeholders} at &quot;UPDATE {$table} SET status = &#039;processing&#039;, lease_token = %s, lease_expires_at = %s, updated_at = %s WHERE id IN ({$placeholders}) AND status IN (&#039;pending&#039;,&#039;retrying&#039;) AND available_at &lt;= %s&quot; |  |
| 150 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 150 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 150 | 24 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_results()\n$table assigned unsafely at line 127. |  |
| 151 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;SELECT id, event_id, payload, attempts FROM {$table} WHERE lease_token = %s AND status = &#039;processing&#039; ORDER BY id ASC&quot; |  |
| 190 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 190 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 190 | 20 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;query()\n$table assigned unsafely at line 127. |  |
| 190 | 26 | WARNING | WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber | Incorrect number of replacements passed to $wpdb-&gt;prepare(). Found 1 replacement parameters, expected 3. |  |
| 192 | 17 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;UPDATE {$table} SET status = &#039;sent&#039;, sent_at = %s, updated_at = %s, last_error = NULL, lease_token = NULL, lease_expires_at = NULL WHERE id IN ({$sent_placeholders}) AND status = &#039;processing&#039; AND lease_token = %s&quot; |  |
| 192 | 17 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$sent_placeholders} at &quot;UPDATE {$table} SET status = &#039;sent&#039;, sent_at = %s, updated_at = %s, last_error = NULL, lease_token = NULL, lease_expires_at = NULL WHERE id IN ({$sent_placeholders}) AND status = &#039;processing&#039; AND lease_token = %s&quot; |  |
| 209 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 209 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 209 | 16 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;query()\n$table assigned unsafely at line 207. |  |
| 210 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;DELETE FROM {$table} WHERE status IN (&#039;pending&#039;,&#039;retrying&#039;,&#039;processing&#039;,&#039;failed&#039;) AND created_at &lt; %s&quot; |  |
| 213 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 213 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 213 | 16 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;query()\n$table assigned unsafely at line 207. |  |
| 214 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;DELETE FROM {$table} WHERE status = &#039;sent&#039; AND sent_at &lt; %s&quot; |  |
| 217 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 217 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 217 | 16 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;query()\n$table assigned unsafely at line 207. |  |
| 218 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$table} at &quot;UPDATE {$table} SET payload = &#039;&#039;, updated_at = %s WHERE status = &#039;failed&#039; AND payload &lt;&gt; &#039;&#039; AND updated_at &lt; %s&quot; |  |
| 298 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 298 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |

## `src/BlocklistSync.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 60 | 106 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/Blocklist.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 289 | 225 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/ApiClient.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 41 | 117 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 46 | 100 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 50 | 136 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 55 | 113 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 111 | 99 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 115 | 91 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 120 | 107 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 128 | 121 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 134 | 114 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 141 | 100 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 157 | 117 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 191 | 99 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 218 | 116 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 375 | 65 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/Scheduler.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 52 | 119 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/KeyRevocation.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 74 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 74 | 20 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 79 | 47 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 79 | 47 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 84 | 92 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 103 | 94 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 119 | 98 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 127 | 96 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |

## `src/Fraud.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 49 | 71 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 279 | 106 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 282 | 95 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 373 | 92 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 375 | 85 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 406 | 78 | ERROR | WordPress.WP.I18n.TextDomainMismatch | Mismatched text domain. Expected 'adoology-wp-plugin' but got 'adoology-for-woocommerce'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/) |
| 455 | 19 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |
| 519 | 19 | WARNING | WordPress.DB.SlowDBQuery.slow_db_query_meta_query | Detected usage of meta_query, possible slow query. |  |

## `.gitignore`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | hidden_files | Hidden files are not permitted. |  |

## `.phpunit.result.cache`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | hidden_files | Hidden files are not permitted. |  |

## `phpunit.xml.dist`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | application_detected | Application files are not permitted. |  |

## `tests/Unit/IncompleteOrderLifecycleTest.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 150 | 26 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound | Global variables defined by a theme/plugin should start with the theme/plugin prefix. Found: &quot;$$key&quot;. |  |
| 627 | 43 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotValidated | Detected usage of a possibly undefined superglobal array index: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]. Check that the array index exists before using it. |  |
| 627 | 43 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 627 | 43 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] |  |
| 834 | 51 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotValidated | Detected usage of a possibly undefined superglobal array index: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]. Check that the array index exists before using it. |  |
| 834 | 51 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 834 | 51 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] |  |
| 950 | 53 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotValidated | Detected usage of a possibly undefined superglobal array index: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]. Check that the array index exists before using it. |  |
| 950 | 53 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 950 | 53 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] |  |
| 951 | 54 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotValidated | Detected usage of a possibly undefined superglobal array index: $_COOKIE[IncompleteOrders::COOKIE_ANON]. Check that the array index exists before using it. |  |
| 951 | 54 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_COOKIE[IncompleteOrders::COOKIE_ANON] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 951 | 54 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_COOKIE[IncompleteOrders::COOKIE_ANON] |  |
| 1014 | 49 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotValidated | Detected usage of a possibly undefined superglobal array index: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]. Check that the array index exists before using it. |  |
| 1014 | 49 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 1014 | 49 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] |  |
| 1080 | 49 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotValidated | Detected usage of a possibly undefined superglobal array index: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]. Check that the array index exists before using it. |  |
| 1080 | 49 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 1080 | 49 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] |  |
| 1098 | 43 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotValidated | Detected usage of a possibly undefined superglobal array index: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]. Check that the array index exists before using it. |  |
| 1098 | 43 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 1098 | 43 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] |  |
| 1229 | 56 | ERROR | WordPress.Security.EscapeOutput.ExceptionNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$url'. | [Docs](https://developer.wordpress.org/apis/security/escaping/) |
| 1493 | 61 | ERROR | WordPress.Security.EscapeOutput.ExceptionNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$query'. | [Docs](https://developer.wordpress.org/apis/security/escaping/) |
| 1499 | 65 | ERROR | WordPress.Security.EscapeOutput.ExceptionNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$query'. | [Docs](https://developer.wordpress.org/apis/security/escaping/) |
| 1513 | 69 | ERROR | WordPress.Security.EscapeOutput.ExceptionNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$query'. | [Docs](https://developer.wordpress.org/apis/security/escaping/) |
| 1564 | 71 | ERROR | WordPress.Security.EscapeOutput.ExceptionNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$condition'. | [Docs](https://developer.wordpress.org/apis/security/escaping/) |
| 1584 | 82 | ERROR | WordPress.Security.EscapeOutput.ExceptionNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$sql'. | [Docs](https://developer.wordpress.org/apis/security/escaping/) |
| 1584 | 94 | ERROR | WordPress.Security.EscapeOutput.ExceptionNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$sql'. | [Docs](https://developer.wordpress.org/apis/security/escaping/) |

## `tests/Unit/ConnectionTest.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 84 | 67 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |
| 172 | 67 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |

## `tests/Unit/ApiClientTest.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 105 | 67 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |

## `tests/stubs.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 79 | 5 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound | Functions declared in the global namespace by a theme/plugin should start with the theme/plugin prefix. Found: &quot;is_wp_error&quot;. |  |
| 86 | 5 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound | Functions declared in the global namespace by a theme/plugin should start with the theme/plugin prefix. Found: &quot;wp_json_encode&quot;. |  |
| 93 | 5 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound | Functions declared in the global namespace by a theme/plugin should start with the theme/plugin prefix. Found: &quot;sanitize_key&quot;. |  |
| 100 | 5 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound | Functions declared in the global namespace by a theme/plugin should start with the theme/plugin prefix. Found: &quot;sanitize_text_field&quot;. |  |
| 102 | 55 | ERROR | WordPress.WP.AlternativeFunctions.strip_tags_strip_tags | strip_tags() is discouraged. Use the more comprehensive wp_strip_all_tags() instead. |  |
| 107 | 5 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound | Functions declared in the global namespace by a theme/plugin should start with the theme/plugin prefix. Found: &quot;absint&quot;. |  |

## `.github`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | WARNING | github_directory | GitHub workflow directory ".github" detected. This directory should not be included in production plugins. |  |

## `review.md`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | WARNING | unexpected_markdown_file | Unexpected markdown file "review.md" detected in plugin root. Only specific markdown files are expected in production plugins. |  |

## `tests/Unit/SettingsTest.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 24 | 28 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_SERVER[&#039;REQUEST_METHOD&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 24 | 28 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_SERVER[&#039;REQUEST_METHOD&#039;] |  |

## `uninstall.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 40 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 40 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 40 | 28 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $webhook_table used in $wpdb-&gt;get_results()\n$webhook_table assigned unsafely at line 31. |  |
| 41 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$webhook_table} at &quot;SELECT webhook_id, name FROM {$webhook_table} WHERE name LIKE %s&quot; |  |
| 46 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 56 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 56 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 56 | 24 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $key_table used in $wpdb-&gt;get_results()\n$key_table assigned unsafely at line 55. |  |
| 57 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$key_table} at &quot;SELECT key_id, description FROM {$key_table} WHERE description = %s OR description LIKE %s OR description LIKE %s&quot; |  |
| 68 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 142 | 5 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 142 | 18 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |
| 143 | 5 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 143 | 18 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |

## `src/Database.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 31 | 28 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 31 | 28 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 31 | 35 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete used in $wpdb-&gt;get_var()\n$incomplete assigned unsafely at line 27. |  |
| 31 | 58 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete} at &quot;SHOW INDEX FROM {$incomplete} WHERE Key_name = %s&quot; |  |
| 33 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 33 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 33 | 20 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $incomplete used in $wpdb-&gt;query()\n$incomplete assigned unsafely at line 27. |  |
| 33 | 26 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$incomplete} at &quot;ALTER TABLE {$incomplete} ADD KEY retention (status, updated_at)&quot; |  |
| 33 | 26 | WARNING | WordPress.DB.DirectDatabaseQuery.SchemaChange | Attempting a database schema change is discouraged. |  |
| 53 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 53 | 17 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
