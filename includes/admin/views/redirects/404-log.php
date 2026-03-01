<?php
defined('ABSPATH') || exit;

global $wpdb;

$log_table = $wpdb->prefix . 'seo_ai_404_log';
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $log_table)) === $log_table;

// Handle CSV export.
if (isset($_GET['seo_ai_export_404']) && check_admin_referer('seo_ai_export_404')) {
    if ($table_exists) {
        $export_where = '1=1';
        $export_params = [];
        if (!empty($_GET['date_from'])) {
            $export_where .= ' AND last_hit >= %s';
            $export_params[] = sanitize_text_field(wp_unslash($_GET['date_from'])) . ' 00:00:00';
        }
        if (!empty($_GET['date_to'])) {
            $export_where .= ' AND last_hit <= %s';
            $export_params[] = sanitize_text_field(wp_unslash($_GET['date_to'])) . ' 23:59:59';
        }
        $query = "SELECT url, hit_count, first_hit, last_hit, referrer, user_agent FROM {$log_table} WHERE {$export_where} ORDER BY hit_count DESC";
        $rows = empty($export_params)
            ? $wpdb->get_results($query)
            : $wpdb->get_results($wpdb->prepare($query, ...$export_params));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=seo-ai-404-log-' . gmdate('Y-m-d') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['URL', 'Hits', 'First Hit', 'Last Hit', 'Referrer', 'User Agent']);
        foreach ($rows as $row) {
            fputcsv($out, [$row->url, $row->hit_count, $row->first_hit, $row->last_hit, $row->referrer, $row->user_agent]);
        }
        fclose($out);
        exit;
    }
}

// Handle bulk delete.
if (isset($_POST['seo_ai_clear_404_log']) && check_admin_referer('seo_ai_clear_404_log')) {
    if ($table_exists) {
        $wpdb->query("TRUNCATE TABLE {$log_table}");
    }
    echo '<div class="notice notice-success"><p>' . esc_html__('404 log cleared.', 'seo-ai') . '</p></div>';
}

// Handle single delete.
if (isset($_GET['delete_404']) && check_admin_referer('seo_ai_delete_404')) {
    $delete_id = absint($_GET['delete_404']);
    if ($table_exists && $delete_id > 0) {
        $wpdb->delete($log_table, ['id' => $delete_id], ['%d']);
    }
}

// Handle create redirect from 404.
if (isset($_POST['seo_ai_create_redirect_from_404']) && check_admin_referer('seo_ai_redirect_from_404')) {
    $source = sanitize_text_field(wp_unslash($_POST['redirect_source'] ?? ''));
    $target = sanitize_text_field(wp_unslash($_POST['redirect_target'] ?? ''));
    $type   = absint($_POST['redirect_type'] ?? 301);

    if ($source) {
        $redirects_table = $wpdb->prefix . 'seo_ai_redirects';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $redirects_table)) === $redirects_table) {
            $wpdb->insert(
                $redirects_table,
                [
                    'source_url'  => $source,
                    'target_url'  => $target,
                    'type'        => $type,
                    'hits'        => 0,
                    'created_at'  => current_time('mysql', true),
                    'updated_at'  => current_time('mysql', true),
                ],
                ['%s', '%s', '%d', '%d', '%s', '%s']
            );
            echo '<div class="notice notice-success"><p>' . esc_html__('Redirect created successfully.', 'seo-ai') . '</p></div>';
        }
    }
}

// Pagination.
$per_page     = 25;
$current_page = max(1, absint($_GET['paged'] ?? 1));
$offset       = ($current_page - 1) * $per_page;
$total_items  = 0;
$items        = [];
$order_by     = sanitize_sql_orderby($_GET['orderby'] ?? 'hit_count DESC') ?: 'hit_count DESC';

if ($table_exists) {
    $total_items = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$log_table}");
    $items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$log_table} ORDER BY hit_count DESC, last_hit DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        )
    );
}

$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap seo-ai-wrap">
    <h1 class="seo-ai-header">
        <span class="seo-ai-logo">SEO AI</span>
        <span>— 404 Log</span>
    </h1>

    <!-- Summary -->
    <div class="seo-ai-card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div>
                <strong><?php echo esc_html($total_items); ?></strong>
                <?php esc_html_e('unique 404 URLs recorded', 'seo-ai'); ?>
            </div>
            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <?php if ($total_items > 0) : ?>
                <!-- Export CSV -->
                <form method="get" style="margin:0;display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="page" value="seo-ai-404-log" />
                    <?php wp_nonce_field('seo_ai_export_404', '_wpnonce', false); ?>
                    <input type="hidden" name="seo_ai_export_404" value="1" />
                    <label style="font-size:12px;"><?php esc_html_e('From:', 'seo-ai'); ?></label>
                    <input type="date" name="date_from" value="" style="max-width:140px;" />
                    <label style="font-size:12px;"><?php esc_html_e('To:', 'seo-ai'); ?></label>
                    <input type="date" name="date_to" value="" style="max-width:140px;" />
                    <button type="submit" class="button">
                        <?php esc_html_e('Export CSV', 'seo-ai'); ?>
                    </button>
                </form>
                <!-- Clear Log -->
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field('seo_ai_clear_404_log'); ?>
                    <button type="submit" name="seo_ai_clear_404_log" value="1" class="button"
                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear the entire 404 log?', 'seo-ai'); ?>');">
                        <?php esc_html_e('Clear Log', 'seo-ai'); ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($items)) : ?>
        <div class="seo-ai-card">
            <p style="text-align:center;color:#6b7280;padding:24px 0;">
                <?php esc_html_e('No 404 errors recorded yet.', 'seo-ai'); ?>
            </p>
        </div>
    <?php else : ?>

    <!-- 404 Log Table -->
    <div class="seo-ai-card" style="padding:0;overflow:auto;">
        <table class="wp-list-table widefat fixed striped" style="border:none;">
            <thead>
                <tr>
                    <th style="width:40%;"><?php esc_html_e('URL', 'seo-ai'); ?></th>
                    <th style="width:8%;text-align:center;"><?php esc_html_e('Hits', 'seo-ai'); ?></th>
                    <th style="width:15%;"><?php esc_html_e('Last Hit', 'seo-ai'); ?></th>
                    <th style="width:15%;"><?php esc_html_e('Referrer', 'seo-ai'); ?></th>
                    <th style="width:10%;"><?php esc_html_e('User Agent', 'seo-ai'); ?></th>
                    <th style="width:12%;"><?php esc_html_e('Actions', 'seo-ai'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) : ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($item->url); ?></strong>
                    </td>
                    <td style="text-align:center;">
                        <span class="seo-ai-badge <?php echo $item->hit_count > 10 ? 'seo-ai-badge-danger' : ($item->hit_count > 3 ? 'seo-ai-badge-warning' : 'seo-ai-badge-muted'); ?>">
                            <?php echo esc_html($item->hit_count); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $last_hit = strtotime($item->last_hit);
                        echo esc_html(human_time_diff($last_hit, time())) . ' ' . esc_html__('ago', 'seo-ai');
                        ?>
                    </td>
                    <td>
                        <?php
                        if (!empty($item->referrer)) {
                            $host = wp_parse_url($item->referrer, PHP_URL_HOST);
                            echo '<span title="' . esc_attr($item->referrer) . '">' . esc_html($host ?: $item->referrer) . '</span>';
                        } else {
                            echo '<span style="color:#9ca3af;">—</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        if (!empty($item->user_agent)) {
                            $short_ua = mb_strimwidth($item->user_agent, 0, 30, '...');
                            echo '<span title="' . esc_attr($item->user_agent) . '">' . esc_html($short_ua) . '</span>';
                        } else {
                            echo '<span style="color:#9ca3af;">—</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <a href="#seo-ai-redirect-modal-<?php echo esc_attr($item->id); ?>"
                           class="button button-small seo-ai-create-redirect-btn"
                           data-source="<?php echo esc_attr($item->url); ?>"
                           data-id="<?php echo esc_attr($item->id); ?>">
                            <?php esc_html_e('Redirect', 'seo-ai'); ?>
                        </a>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('delete_404', $item->id), 'seo_ai_delete_404')); ?>"
                           class="button button-small"
                           onclick="return confirm('<?php esc_attr_e('Delete this entry?', 'seo-ai'); ?>');">
                            <?php esc_html_e('Delete', 'seo-ai'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
    <div class="tablenav bottom" style="margin-top:12px;">
        <div class="tablenav-pages">
            <?php
            echo paginate_links([
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total'     => $total_pages,
                'current'   => $current_page,
            ]);
            ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- Redirect Modal (hidden, shown via JS) -->
<div id="seo-ai-redirect-modal" style="display:none;">
    <div class="seo-ai-card" style="max-width:500px;margin:40px auto;">
        <h2><?php esc_html_e('Create Redirect', 'seo-ai'); ?></h2>
        <form method="post">
            <?php wp_nonce_field('seo_ai_redirect_from_404'); ?>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e('Source URL', 'seo-ai'); ?></label></th>
                    <td><input type="text" name="redirect_source" id="seo-ai-redirect-source" class="regular-text" readonly /></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('Target URL', 'seo-ai'); ?></label></th>
                    <td><input type="text" name="redirect_target" class="regular-text" placeholder="/new-url/" /></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('Type', 'seo-ai'); ?></label></th>
                    <td>
                        <select name="redirect_type">
                            <option value="301"><?php esc_html_e('301 Permanent', 'seo-ai'); ?></option>
                            <option value="302"><?php esc_html_e('302 Found', 'seo-ai'); ?></option>
                            <option value="410"><?php esc_html_e('410 Deleted', 'seo-ai'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <p>
                <button type="submit" name="seo_ai_create_redirect_from_404" value="1" class="button button-primary">
                    <?php esc_html_e('Create Redirect', 'seo-ai'); ?>
                </button>
                <button type="button" class="button seo-ai-close-modal"><?php esc_html_e('Cancel', 'seo-ai'); ?></button>
            </p>
        </form>
    </div>
</div>

<script>
(function($) {
    $(document).on('click', '.seo-ai-create-redirect-btn', function(e) {
        e.preventDefault();
        var source = $(this).data('source');
        $('#seo-ai-redirect-source').val(source);
        $('#seo-ai-redirect-modal').show();
    });
    $(document).on('click', '.seo-ai-close-modal', function() {
        $('#seo-ai-redirect-modal').hide();
    });
})(jQuery);
</script>
