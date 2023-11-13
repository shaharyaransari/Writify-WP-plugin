<?php
/**
 * Plugin Name:       Writify
 * Description:       Score IELTS Essays x GPT
 * Version:           1.0.5
 * Copyright: © 2023-2026 RLT
 */

// Define the plugin constants if not defined.
defined("OPAIGFRLT_URL") or define("OPAIGFRLT_URL", plugin_dir_url(__FILE__));
defined("OPAIGFRLT_PATH") or define("OPAIGFRLT_PATH", plugin_dir_path(__FILE__));
defined("OPAIGFRLT_LOG") or define("OPAIGFRLT_LOG", false);

add_filter("gform_gravityforms-openai_pre_process_feeds", '__return_empty_string');

function writify_get_feeds($form_id = null)
{
    global $wpdb;

    $form_filter = is_numeric($form_id)
        ? $wpdb->prepare("AND form_id=%d", absint($form_id))
        : "";

    $sql = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gf_addon_feed
        WHERE addon_slug=%s {$form_filter} ORDER BY `feed_order`, `id` ASC",
        "gravityforms-openai"
    );

    $results = $wpdb->get_results($sql, ARRAY_A);
    foreach ($results as &$result) {
        $result["meta"] = json_decode($result["meta"], true);
    }

    return $results;
}

function writify_register_routes()
{
    register_rest_route(
        'writify/v1',
        '/event_stream_openai/',
        array(
            'methods' => 'GET',
            'callback' => 'event_stream_openai',
            'permission_callback' => '__return_true',
            // If you want to restrict access, modify this
        )
    );
}
add_action('rest_api_init', 'writify_register_routes');

add_action("wp_footer", "enqueue_scripts_on_result_pages", 9999);
function enqueue_scripts_on_result_pages()
{
    global $post;
    if (!$post) {
        return;
    }

    $slug = $post->post_name;

    // Check if the page slug begins with "result"
    if (strpos($slug, 'result') !== 0) {
        return;
    }

    // Enqueue Remarkable Markdown Parser
    wp_enqueue_script('remarkable', 'https://cdn.jsdelivr.net/remarkable/1.7.1/remarkable.min.js', array(), null, true);

    // Enqueue Grammarly Editor SDK
    wp_enqueue_script('grammarly-editor-sdk', 'https://js.grammarly.com/grammarly-editor-sdk@2.5?clientId=client_MpGXzibWoFirSMscGdJ4Pt&packageName=%40grammarly%2Feditor-sdk', array(), null, true);

    // Enqueue the text interaction handler script
    wp_enqueue_script('vocab-interaction-handler', plugin_dir_url(__FILE__) . 'Assets/js/vocab_interaction_handler.js', array('jquery'), '1.0.0', true);

    // Enqueue the user role event stream script
    wp_enqueue_script('writify-event-stream', plugin_dir_url(__FILE__) . 'Assets/js/writify_event_stream.js', array('jquery'), '1.0.0', true);

    // Enqueue the result page styles
    wp_enqueue_style('result-page-styles', plugin_dir_url(__FILE__) . 'Assets/css/result_page_styles.css', array(), '1.0.0');

    // Pass dynamic data to the script
    $form_id = isset($_GET['form_id']) ? (int) sanitize_text_field($_GET['form_id']) : 0;
    $entry_id = isset($_GET['entry_id']) ? (int) sanitize_text_field($_GET['entry_id']) : 0;
    $nonce = wp_create_nonce('wp_rest');

    $data_to_pass = array(
        'form_id' => $form_id,
        'entry_id' => $entry_id,
        'nonce' => $nonce
    );

    wp_localize_script('writify-event-stream', 'writifyAjaxData', $data_to_pass);

    // Additional inline scripts here if necessary
}

function writify_enqueue_scripts()
{
    // Get current post
    global $post;

    // Check if we're inside a post and get the slug
    if (is_a($post, 'WP_Post')) {
        $slug = $post->post_name;

        // Enqueue the script only if the slug starts with 'result'
        if (substr($slug, 0, 6) === 'result') {
            wp_enqueue_script('writify-docx-export', plugin_dir_url(__FILE__) . 'Assets/js/docx_export.js', array('jquery'), '1.0.0', true);

            // Get current user's data
            $current_user = wp_get_current_user();

            // Prepare data to pass to the script
            $data_to_pass = array(
                'firstName' => $current_user->user_firstname,
                'lastName' => $current_user->user_lastname
            );

            // Localize the script with the data
            wp_localize_script('writify-docx-export', 'writifyUserData', $data_to_pass);
        }
    }
}

add_action('wp_enqueue_scripts', 'writify_enqueue_scripts');


function writify_make_request($feed, $entry, $form)
{
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();

    //$headers = $GWiz_GF_OpenAI_Object->get_headers();

    $endpoint = $feed["meta"]["endpoint"];

    if ($endpoint === "chat/completions") {
        $model = $feed["meta"]["chat_completions_model"];
        $message = $feed["meta"]["chat_completions_message"];

        // Parse the merge tags in the message.
        $message = GFCommon::replace_variables(
            $message,
            $form,
            $entry,
            false,
            false,
            false,
            "text"
        );

        GFAPI::add_note(
            $entry["id"],
            0,
            "OpenAI Request (" . $feed["meta"]["feed_name"] . ")",
            sprintf(
                __(
                    "Sent request to OpenAI chat/completions endpoint.",
                    "gravityforms-openai"
                )
            )
        );

        // translators: placeholders are the feed name, model, prompt
        $GWiz_GF_OpenAI_Object->log_debug(
            __METHOD__ .
            "(): " .
            sprintf(
                __(
                    'Sent request to OpenAI. Feed: %1$s, Endpoint: chat, Model: %2$s, Message: %3$s',
                    "gravityforms-openai"
                ),
                $feed["meta"]["feed_name"],
                $model,
                $message
            )
        );

        $body = [
            "messages" => [
                [
                    "role" => "user",
                    "content" => $message,
                ],
            ],
            "model" => $model,
        ];

        // Identify the user role or membership title from the API request
        $primary_identifier = isset($_REQUEST["user_identifier"]) ? sanitize_text_field($_REQUEST["user_identifier"]) : 'default';

        // Log primary role or membership title for debugging
        $GWiz_GF_OpenAI_Object->log_debug("Primary identifier (role or membership): " . $primary_identifier);

        // Get the saved API base for the user role or membership from the feed settings
        $option_name = 'api_base_' . $primary_identifier;
        $api_base = rgar($feed['meta'], $option_name, 'https://api.openai.com/v1/');

        // Log API base for debugging
        $GWiz_GF_OpenAI_Object->log_debug("API Base: " . $api_base);

        // Log the entirety of feed['meta'] for debugging
        $GWiz_GF_OpenAI_Object->log_debug("Feed Meta Data: " . print_r($feed['meta'], true));

        $url = $api_base . $endpoint;

        if ($api_base === 'https://writify.openai.azure.com/openai/deployments/IELTS-Writify/') {
            $url .= '?api-version=2023-03-15-preview';
        }

        $body["max_tokens"] = (float) rgar(
            $feed["meta"],
            $endpoint . "_" . "max_tokens",
            $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
                "max_tokens"
            ]
        );
        $body["temperature"] = (float) rgar(
            $feed["meta"],
            $endpoint . "_" . "temperature",
            $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
                "temperature"
            ]
        );
        $body["top_p"] = (float) rgar(
            $feed["meta"],
            $endpoint . "_" . "top_p",
            $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
                "top_p"
            ]
        );
        $body["frequency_penalty"] = (float) rgar(
            $feed["meta"],
            $endpoint . "_" . "frequency_penalty",
            $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
                "frequency_penalty"
            ]
        );
        $body["presence_penalty"] = (float) rgar(
            $feed["meta"],
            $endpoint . "_" . "presence_penalty",
            $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
                "presence_penalty"
            ]
        );

        $body["stream"] = true;

        // Add retry mechanism
        $max_retries = 20;
        $retry_count = 0;

        // Get the timeout setting from the feed meta, with a fallback default
        $default_timeout = rgar(rgar($this->default_settings, $endpoint), 'timeout', 180); // Default to 180 seconds if not set
        $timeout = (int) rgar($feed["meta"], $endpoint . "_timeout", $default_timeout);

        do {
            $retry = false;

            // Regenerate headers before each retry
            $headers = $GWiz_GF_OpenAI_Object->get_headers();

            // Set the new headers
            $header = [
                "Content-Type: " . $headers["Content-Type"],
                "Authorization: " . $headers["Authorization"],
                "api-key: " . $headers["api-key"]
            ];

            if (isset($headers['OpenAI-Organization'])) {
                $header[] = "OpenAI-Organization: " . $headers['OpenAI-Organization'];
            }

            $post_json = json_encode($body);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            // Set the dynamic timeout
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

            // Log the Request Details
            $GWiz_GF_OpenAI_Object->log_debug("Request URL: " . $url);
            $GWiz_GF_OpenAI_Object->log_debug("Request Headers: " . json_encode($header));
            $GWiz_GF_OpenAI_Object->log_debug("Request Body: " . $post_json);

            $object = new stdClass();
            $object->res = "";
            $object->error = "";

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($object) {
                $pop_arr = explode("data: ", $data);

                foreach ($pop_arr as $pop_item) {
                    if (trim($pop_item) === '[DONE]') {
                        continue; // Skip this iteration and don't process or echo the [DONE] segment.
                    }

                    $pop_js = json_decode($pop_item, true);
                    if (isset($pop_js["choices"])) {
                        $line = isset($pop_js["choices"][0]["delta"]["content"])
                            ? $pop_js["choices"][0]["delta"]["content"]
                            : "";
                        if (!empty($line) || $line == "1" || $line == "0") {
                            $object->res .= $line;
                        }
                    } elseif (isset($pop_js['error'])) {
                        if (isset($pop_js['error']['message'])) {
                            $object->error = $pop_js['error']['message'];
                        }
                        if (isset($pop_js['error']['detail'])) {
                            $object->error = $pop_js['error']['detail'];
                        }
                    }

                    echo "data: " . $pop_item . PHP_EOL;
                }

                //writify_chatgpt_writelog(trim($data)); // Log the raw JSON

                return strlen($data);
            });

            curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // Check and Log cURL Errors
            if (curl_errno($ch)) {
                $error_msg = curl_error($ch);
                $GWiz_GF_OpenAI_Object->log_debug("cURL Error: " . $error_msg);
            }
            curl_close($ch);

            if (!empty($object->res)) {
                GFAPI::add_note(
                    $entry["id"],
                    0,
                    "OpenAI Response (" . $feed["meta"]["feed_name"] . ")",
                    $object->res
                );
                $entry = $GWiz_GF_OpenAI_Object->maybe_save_result_to_field(
                    $feed,
                    $entry,
                    $form,
                    $object->res
                );

                // Update the entry in the database
                $result = GFAPI::update_entry($entry);
                if (is_wp_error($result)) {
                    error_log('Failed to update entry: ' . $result->get_error_message());
                }

            } else {
                if ($http_status !== 200 || !empty($object->error)) {
                    $retry_count++;
                    if ($retry_count <= $max_retries) {
                        $retry = true;
                        sleep(2); // Optional: add sleep time before retrying
                        GFAPI::add_note(
                            $entry["id"],
                            0,
                            "OpenAI Error Response (" . $feed["meta"]["feed_name"] . ")",
                            $object->error
                        );
                        $GWiz_GF_OpenAI_Object->add_feed_error(
                            $object->error,
                            $feed,
                            $entry,
                            $form
                        );
                    } else {
                        GFAPI::add_note(
                            $entry["id"],
                            0,
                            "OpenAI Error Response (" . $feed["meta"]["feed_name"] . ")",
                            $object->error
                        );
                        $GWiz_GF_OpenAI_Object->add_feed_error(
                            $object->error,
                            $feed,
                            $entry,
                            $form
                        );
                        return $object;
                    }
                }
            }
        } while ($retry);

        gform_add_meta(
            $entry["id"],
            "openai_response_" . $feed["id"],
            $object->res
        );

        return $entry;
    } else {
        $object = new stdClass();
        $object->error = "Not allowed";
        return $object;
    }
}

add_action("wp_ajax_event_stream_openai", "event_stream_openai");
add_action("wp_ajax_nopriv_event_stream_openai", "event_stream_openai");
add_action('wp_ajax_writify_get_user_role', 'writify_get_user_role');
add_action('wp_ajax_nopriv_writify_get_user_role', 'writify_get_user_role');

function writify_get_user_role()
{
    $current_user = wp_get_current_user();

    // Default role/membership
    $primary_identifier = 'default';

    $has_memberpress = false; // This flag will indicate if MemberPress is active

    // Check for MemberPress memberships
    if (class_exists('MeprUser')) {
        $has_memberpress = true; // Set the flag to true as MemberPress is active
        $mepr_user = new MeprUser($current_user->ID);
        $active_memberships = $mepr_user->active_product_subscriptions();

        if (!empty($active_memberships)) {
            $primary_membership = get_post($active_memberships[0]);
            if ($primary_membership) {
                $primary_identifier = $primary_membership->post_name; // User has a membership
            }
        } else {
            $primary_identifier = 'No_membership'; // No active membership
        }
    }

    // Fallback to roles if MemberPress is not active
    if (!$has_memberpress && !empty($current_user->roles)) {
        $primary_identifier = $current_user->roles[0];
    }

    echo json_encode(['role' => $primary_identifier]);
    wp_die();
}

function event_stream_openai(WP_REST_Request $request)
{

    if (!headers_sent()) {
        @ini_set("zlib.output_compression", 0);
        ob_implicit_flush(true);
        ob_end_flush();

        header("Content-Type: text/event-stream");
        header("Cache-Control: no-cache");

        $_slug = "gravityforms-openai";
        $form_id = isset($_GET["form_id"])
            ? (int) sanitize_text_field($_GET["form_id"])
            : 0;
        $entry_id = isset($_GET["entry_id"])
            ? (int) sanitize_text_field($_GET["entry_id"])
            : 0;
        if ($form_id < 1 || $entry_id < 1) {
            echo "data: [ALLDONE]" . PHP_EOL;
            echo PHP_EOL;
            flush();
        }

        // New function to output SSE data.
        $send_data = function ($data) {
            echo "data: " . $data . PHP_EOL;
            echo PHP_EOL;
            flush();
        };

        $send_data("[DIVINDEX-0]");

        $feeds = writify_get_feeds($form_id);

        if (empty($feeds)) {
            $send_data("[ALLDONE]");
        }

        $form = GFAPI::get_form($form_id);
        $entry = GFAPI::get_entry($entry_id);

        // Get current processed feeds.
        $meta = gform_get_meta($entry["id"], "processed_feeds");

        // If no feeds have been processed for this entry, initialize the meta array.
        if (empty($meta)) {
            $meta = [];
        }

        $processed_feeds = isset($meta[$_slug]) ? $meta[$_slug] : "";
        if (!is_array($processed_feeds)) {
            $processed_feeds = [];
        }

        // Initialize a flag to check if any feeds were processed.
        $feeds_processed = false;

        // Loop through feeds.
        $feed_index = 1;
        foreach ($feeds as $feed) {
            // Get the feed name.
            $feed_name = rgempty("feed_name", $feed["meta"])
                ? rgar($feed["meta"], "feedName")
                : rgar($feed["meta"], "feed_name");

            $end_point = isset($feed["meta"]["endpoint"])
                ? $feed["meta"]["endpoint"]
                : "";
            $field_id = isset(
                $feed["meta"][$end_point . "_map_result_to_field"]
                )
                ? $feed["meta"][$end_point . "_map_result_to_field"]
                : 0;

            // If this feed is inactive, set a flag and continue to the next feed.
            if (!$feed["is_active"]) {
                $feeds_processed = true; // Mark that feeds were processed.
                continue;
            }

            if (in_array((string) $feed["id"], $processed_feeds)) {
                $lines = explode("<br />", $entry[$field_id]);
                foreach ($lines as $line) {
                    $object = new stdClass();
                    if (empty(trim($line))) {
                        $line = "\r\n";
                    } else {
                        $line = trim($line) . "\r\n";
                    }
                    $object->content = $line;
                    $send_data(
                        json_encode(["choices" => [["delta" => $object]]])
                    );
                }
                $send_data("[DONE]");
                $send_data("[DIVINDEX-" . $feed_index . "]");
            } else {
                //writify_chatgpt_writelog("Processing " . $feed_name . " is active!");

                // All requirements are met; process feed.
                $returned_entry = writify_make_request($feed, $entry, $form);

                // If returned value from the processed feed call is an array containing an id, set the entry to its value.
                if (is_array($returned_entry)) {
                    $entry = $returned_entry;
                    // Adding this feed to the list of processed feeds
                    $processed_feeds[] = $feed["id"];

                    // Update the processed_feeds metadata after each feed is processed
                    $meta[$_slug] = $processed_feeds;
                    gform_update_meta($entry["id"], "processed_feeds", $meta);
                } else {
                    //skip
                }
                $send_data("[DONE]");
                $send_data("[DIVINDEX-" . $feed_index . "]");
            }
            $feed_index++;
        }

        gform_update_meta($entry["id"], "{$_slug}_is_fulfilled", true);

        // If any feeds were processed, save the processed feed IDs.
        if (!empty($processed_feeds)) {
            // Add this Add-On's processed feeds to the entry meta.
            $meta[$_slug] = $processed_feeds;

            // Update the entry meta.
            gform_update_meta($entry["id"], "processed_feeds", $meta);
        }

        $send_data("[ALLDONE]");
        die();
    }
    $send_data("[ALLDONE]");
    die();
}
function writify_chatgpt_writelog($log_data)
{
    if (function_exists('error_log')) {
        error_log(date("Y-m-d H:i:s") . " - " . $log_data . PHP_EOL, 3, plugin_dir_path(__FILE__) . 'chatgpt_log.txt');
    }
}