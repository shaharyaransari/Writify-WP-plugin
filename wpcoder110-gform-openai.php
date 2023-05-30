<?php
/**
 * Plugin Name:       Gform OpenAi Extender
 * Description:       Powered with AI and ChatGpt
 * Version:           1.0.0
 * Copyright: © 2023-2026 RLT
 */

// Define the plugin constants if not defined.
defined("OPAIGFRLT_URL") or define("OPAIGFRLT_URL", plugin_dir_url(__FILE__));
defined("OPAIGFRLT_PATH") or define("OPAIGFRLT_PATH", plugin_dir_path(__FILE__));
defined("OPAIGFRLT_LOG") or define("OPAIGFRLT_LOG", false);

add_filter("gform_gravityforms-openai_pre_process_feeds", '__return_empty_string');

function wpcoder110_get_feeds($form_id = null)
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

add_action("wp_footer", "wpcoder110_ajax_calls", 9999);
function wpcoder110_ajax_calls()
{
    // Moved repeated code to a single function.
    $get_int_val = function ($key) {
        return isset($_GET[$key]) ? (int) sanitize_text_field($_GET[$key]) : 0;
    };

    $form_id = $get_int_val("form_id");
    $entry_id = $get_int_val("entry_id");
    ?>
    <style>
        .elementor-shortcode {
            margin-top: 10px;
        }

        .elementor-shortcode ol {
            padding-left: 1rem;
        }

        .elementor-shortcode li {
            padding-left: 5px;
        }

        .elementor-shortcode p {
            white-space: pre-wrap;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        var div_index = 0, div_index_str = '';
        var buffer = ""; // Buffer for holding messages
        const source = new EventSource("<?php echo admin_url(
            "admin-ajax.php"
        ); ?>?action=event_stream_openai&form_id=<?php echo $form_id; ?>&entry_id=<?php echo $entry_id; ?>&nonce=<?php echo wp_create_nonce(
                   "ssevent_stream_openai"
               ); ?>");
        source.onmessage = function (event) {
            if (event.data == "[ALLDONE]") {
                source.close();
            } else if (event.data.startsWith("[DIVINDEX-")) {
                div_index_str = event.data.replace("[DIVINDEX-", "").replace("]", "");
                div_index = parseInt(div_index_str);
                console.log(div_index);
                jQuery('.response-div-' + (div_index)).css('display', 'flex');
                jQuery('.response-div-divider' + (div_index)).show();
            } else if (event.data == "[DONE]") {
                // When a message is done, convert the buffer to HTML and display it
                var html = marked.parse(buffer);
                jQuery('.response-div-' + div_index).find('.preloader-icon').hide();
                var current_div = jQuery('.response-div-' + div_index).find('.elementor-shortcode');
                current_div.html(html); // Replace the current HTML content with the processed markdown
                // Clear the buffer
                buffer = "";
            } else {
                // Add the message to the buffer
                text = JSON.parse(event.data).choices[0].delta.content;
                if (text !== undefined) {
                    buffer += text;
                    // Convert the buffer to HTML and display it
                    var html = marked.parse(buffer);
                    jQuery('.response-div-' + div_index).find('.preloader-icon').hide();
                    var current_div = jQuery('.response-div-' + div_index).find('.elementor-shortcode');
                    current_div.html(html); // Replace the current HTML content with the processed markdown
                }
            }
        };
        source.onerror = function (event) {
            div_index = 0;
            source.close();
        };
    </script>
    <?php
}

function wpcoder110_make_request($feed, $entry, $form)
{
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();

    $headers = $GWiz_GF_OpenAI_Object->get_headers();

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

        // Use the user-specified API base if available, else use default
        $api_base = rgar($feed['meta'], 'api_base', 'https://api.openai.com/v1/');

        $url = $api_base . $endpoint;

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

        $header = [
            "Content-Type: " . $headers["Content-Type"],
            "Authorization: " . $headers["Authorization"],
        ];

        // Add retry mechanism
        $max_retries = 150;
        $retry_count = 0;

        do {
            $retry = false;

            $post_json = json_encode($body);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

            $object = new stdClass();
            $object->res = "";
            $object->error = "";

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($object) {
                $pop_arr = explode("data: ", $data);

                $pop_js_2 = isset($pop_arr[2])
                    ? json_decode($pop_arr[2], true)
                    : "";

                if (isset($pop_js_2["choices"])) {
                    $line = isset($pop_js_2["choices"][0]["delta"]["content"])
                        ? $pop_js_2["choices"][0]["delta"]["content"]
                        : "";
                    if (!empty($line) || $line == "1" || $line == "0") {
                        $object->res .= $line;
                    }
                } else {
                    if (isset($pop_arr[1])) {
                        $pop_js = json_decode($pop_arr[1], true);
                        if (isset($pop_js["choices"])) {
                            $line = isset($pop_js["choices"][0]["delta"]["content"])
                                ? $pop_js["choices"][0]["delta"]["content"]
                                : "";
                            if (!empty($line) || $line == "1" || $line == "0") {
                                $object->res .= $line;
                            }
                        }
                    } else {
                        $pop_js = json_decode($data);
                        if (isset($pop_js->error)) {
                            if (isset($pop_js->error->message)) {
                                $object->error = $pop_js->error->message;
                            }
                            if (isset($pop_js->error->detail)) {
                                $object->error = $pop_js->error->detail;
                            }
                        }
                    }
                }
                //wpcoder110_chatgpt_writelog(trim($data)); // Add this line to log the raw JSON

                echo $data;
                return strlen($data);
            });

            curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
            } else {
                if ($http_status !== 200 || !empty($object->error)) {
                    $retry_count++;
                    if ($retry_count <= $max_retries) {
                        $retry = true;
                        sleep(2); // Optional: add sleep time before retrying
                        GFAPI::add_note(
                            $entry["id"],
                            0,
                            "OpenAI Response (" . $feed["meta"]["feed_name"] . ")",
                            $object->res
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
                            "OpenAI Response (" . $feed["meta"]["feed_name"] . ")",
                            $object->res
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

function event_stream_openai()
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

        $feeds = wpcoder110_get_feeds($form_id);

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
                //wpcoder110_chatgpt_writelog("Processing " . $feed_name . " is active!");

                // All requirements are met; process feed.
                $returned_entry = wpcoder110_make_request($feed, $entry, $form);

                // If returned value from the process feed call is an array containing an id, set the entry to its value.
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
function wpcoder110_chatgpt_writelog($log_data)
{
    if (function_exists('error_log')) {
        error_log(date("Y-m-d H:i:s") . " - " . $log_data . PHP_EOL, 3, plugin_dir_path(__FILE__) . 'chatgpt_log.txt');
    }
}