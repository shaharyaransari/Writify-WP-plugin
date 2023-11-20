<?php
function check_post_plagiarism($post_id)
{
    error_log('Starting plagiarism check for post ID: ' . $post_id);

    $post = get_post($post_id);
    if (!$post) {
        error_log('Failed to retrieve post for plagiarism check.');
        return;
    }

    $full_content = $post->post_content; // Get the full content of the post
    error_log('Retrieved post content for plagiarism check: ' . $full_content);

    // Extract content from the beginning to the "<h3>Vocabulary Improvements</h3>" tag
    $target_tag = '<hr />';
    $tag_position = strpos($full_content, $target_tag);
    $content_to_check = $tag_position !== false ? substr($full_content, 0, $tag_position) : $full_content;

    // Strip HTML tags from the content
    $content_to_check = strip_tags($content_to_check);
    // Log the content being sent to the API for debugging
    error_log('Content sent to API: ' . $content_to_check);

    // Prepare the data and headers for the API request
    $api_url = 'https://papersowl.com/plagiarism-checker-send-data';
    $headers = [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:100.0) Gecko/20100101 Firefox/100.0',
        'X-Requested-With' => 'XMLHttpRequest',
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Cookie' => 'affiliate_user=a%3A3%3A%7Bs%3A9%3A%22affiliate%22%3Bs%3A9%3A%22papersowl%22%3Bs%3A6%3A%22medium%22%3Bs%3A9%3A%22papersowl%22%3Bs%3A8%3A%22campaign%22%3Bs%3A9%3A%22papersowl%22%3B%7D', // Add your cookie here
    ];
    $body = [
        'plagchecker_locale' => 'en',
        'text' => $content_to_check
    ];

    error_log('Body sent to API: ' . print_r($body, true));

    // Use WordPress HTTP API for the request
    $response = wp_remote_post($api_url, [
        'headers' => $headers,
        'body' => $body,
        'timeout' => 120, // Timeout set to 120 seconds (2 minutes)
    ]);

    if (is_wp_error($response)) {
        error_log('Error in plagiarism check request: ' . $response->get_error_message());
        return;
    }

    error_log('Received response from plagiarism check API.');

    $response_body = wp_remote_retrieve_body($response);
    if (empty($response_body)) {
        error_log('Empty response body from plagiarism check API.');
        return;
    }

    // Log the entire API response body for debugging
    error_log('API Response Body: ' . $response_body);

    $result = json_decode($response_body, true);
    if (!$result) {
        error_log('Failed to decode JSON response from plagiarism check API. Response: ' . $response_body);
        return;
    }

    // Store the Turnitin index in a custom field
    if (isset($result['percent'])) {
        $turnitin_index = 100 - floatval($result['percent']);
        update_post_meta($post_id, 'turnitin_index', $turnitin_index);
        error_log('Turnitin index updated for post ID: ' . $post_id . ' with value: ' . $turnitin_index);
    } else {
        error_log('Turnitin index not found in plagiarism check API response.');
    }
}


function schedule_post_checks($post_id, $post, $update)
{
    // Schedule the plagiarism check to occur 1 minute after the post is saved
    wp_schedule_single_event(time() + 60, 'check_post_plagiarism', array($post_id));
}
add_action('wp_insert_post', 'schedule_post_checks', 10, 3);

add_action('check_post_plagiarism', 'check_post_plagiarism');

function add_turnitin_index_column($columns)
{
    $new_columns = array();

    foreach ($columns as $key => $title) {
        // Add the Turnitin Index column after the 'tags' column
        if ($key === 'tags') {
            $new_columns[$key] = $title;
            $new_columns['turnitin_index'] = 'Turnitin Index';
        } else {
            $new_columns[$key] = $title;
        }
    }

    return $new_columns;
}
add_filter('manage_posts_columns', 'add_turnitin_index_column');

function display_turnitin_index_column($column, $post_id)
{
    if ('turnitin_index' === $column) {
        $turnitin_index = get_post_meta($post_id, 'turnitin_index', true);
        echo $turnitin_index ? esc_html($turnitin_index) : 'N/A';
    }
}
add_action('manage_posts_custom_column', 'display_turnitin_index_column', 10, 2);
function turnitin_index_filter_dropdown()
{
    global $typenow;

    if ($typenow == 'post') { // Change 'post' to your custom post type if needed
        $selected_value = isset($_GET['turnitin_index_value']) ? $_GET['turnitin_index_value'] : '';
        $selected_operator = isset($_GET['turnitin_index_operator']) ? $_GET['turnitin_index_operator'] : '';
        ?>
        <label for="turnitin_index_filter">
            <?php _e('Filter by Turnitin Index:', 'textdomain'); ?>
        </label>
        <input type="number" name="turnitin_index_value" placeholder="Enter value"
            value="<?php echo esc_attr($selected_value); ?>" />
        <select name="turnitin_index_operator">
            <option value="">
                <?php _e('Select operator', 'textdomain'); ?>
            </option>
            <option value="greater" <?php selected($selected_operator, 'greater'); ?>>
                <?php _e('Greater than', 'textdomain'); ?>
            </option>
            <option value="less" <?php selected($selected_operator, 'less'); ?>>
                <?php _e('Less than', 'textdomain'); ?>
            </option>
            <option value="equal" <?php selected($selected_operator, 'equal'); ?>>
                <?php _e('Equal to', 'textdomain'); ?>
            </option>
        </select>
        <?php
    }
}
add_action('restrict_manage_posts', 'turnitin_index_filter_dropdown');


function filter_posts_by_turnitin_index($query)
{
    global $pagenow;

    if (is_admin() && $pagenow == 'edit.php' && isset($_GET['turnitin_index_value'], $_GET['turnitin_index_operator']) && $_GET['turnitin_index_value'] != '') {
        $value = $_GET['turnitin_index_value'];
        $operator = $_GET['turnitin_index_operator'];

        switch ($operator) {
            case 'greater':
                $compare = '>';
                break;
            case 'less':
                $compare = '<';
                break;
            case 'equal':
                $compare = '=';
                break;
            default:
                return; // If the operator is not recognized, exit the function
        }

        $query->set(
            'meta_query',
            array(
                array(
                    'key' => 'turnitin_index',
                    'value' => $value,
                    'compare' => $compare,
                    'type' => 'NUMERIC'
                )
            )
        );
    }
}
add_filter('parse_query', 'filter_posts_by_turnitin_index');
