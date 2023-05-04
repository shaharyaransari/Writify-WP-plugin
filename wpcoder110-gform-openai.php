<?php
/**
 * Plugin Name:       Gform OpenAi Extender
 * Description:       Powered with AI and ChatGpt
 * Version:           1.0.0
 * Copyright: © 2023-2026 RLT
 */

/*
 * Plugin constants
 */
if (!defined("OPAIGFRLT_URL")) {
    define("OPAIGFRLT_URL", plugin_dir_url(__FILE__));
}
if (!defined("OPAIGFRLT_PATH")) {
    define("OPAIGFRLT_PATH", plugin_dir_path(__FILE__));
}
define("OPAIGFRLT_LOG", false);

add_filter("gform_gravityforms-openai_pre_process_feeds", function ($feeds) {
    return "";
});

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

    // wpcoder110_chatgpt_writelog(print_r($results, true));

    return $results;
}

add_action("wp_footer", "wpcoder110_ajax_calls", 9999);
function wpcoder110_ajax_calls()
{
    $form_id = isset($_GET["form_id"])
        ? (int) sanitize_text_field($_GET["form_id"])
        : 0;
    $entry_id = isset($_GET["entry_id"])
        ? (int) sanitize_text_field($_GET["entry_id"])
        : 0;
    ?>
    <style>.elementor-shortcode{margin-top: 10px;}</style>
    <script>
        var div_index = 0;

        function initEventSource() {
            const source = new EventSource("<?php echo admin_url(
                "admin-ajax.php"
            ); ?>?action=event_stream_openai&form_id=<?php echo $form_id; ?>&entry_id=<?php echo $entry_id; ?>&nonce=<?php echo wp_create_nonce(
                "ssevent_stream_openai"
            ); ?>");

            source.onmessage = function (event) {
                // ... (rest of the onmessage event code)
            };

            source.onerror = function(event) {
                if (event.target.readyState === EventSource.CLOSED) {
                    console.log('Connection closed, retrying in 5 seconds...');
                    setTimeout(() => {
                        initEventSource();
                    }, 5000);
                } else if (event.target.readyState === EventSource.CONNECTING) {
                    console.log('Reconnecting...');
                }
            };
        }

        initEventSource();
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

        $url = "https://api.openai.com/v1/" . $endpoint;

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

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (
            $object
        ) {
            $pop_arr = explode("data: ", $data);

            $pop_js_2 = isset($pop_arr[2])
                ? json_decode($pop_arr[2], true)
                : "";

            if (isset($pop_js_2["choices"])) {
                $line = isset($pop_js_2["choices"][0]["delta"]["content"])
                    ? $pop_js_2["choices"][0]["delta"]["content"]
                    : "";
                if (!empty($line) || $line == "1" || $line == "0") {
                    if (strpos($line, "\n") !== false) {
                        $object->res .= nl2br($line);
                    } else {
                        $object->res .= $line;
                    }
                }
            } else {
                if (isset($pop_arr[1])) {
                    $pop_js = json_decode($pop_arr[1], true);
                    if (isset($pop_js["choices"])) {
                        $line = isset($pop_js["choices"][0]["delta"]["content"])
                            ? $pop_js["choices"][0]["delta"]["content"]
                            : "";
                        if (!empty($line) || $line == "1" || $line == "0") {
                            if (strpos($line, "\n") !== false) {
                                $object->res .= nl2br($line);
                                wpcoder110_chatgpt_writelog(nl2br($line));
                            } else {
                                $object->res .= $line;
                                wpcoder110_chatgpt_writelog($line);
                            }
                        }
                    }
                } else {
                    $pop_js = json_decode($data);
                    $object->error = $pop_js->error->message;
                }
            }

            echo $data;
            return strlen($data);
        });

        curl_exec($ch);
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
            $GWiz_GF_OpenAI_Object->add_feed_error(
                $object->error,
                $feed,
                $entry,
                $form
            );
            return $object;
        }

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

        $feeds = wpcoder110_get_feeds($form_id);

        if (empty($feeds)) {
            echo "data: [ALLDONE]" . PHP_EOL;
            echo PHP_EOL;
            flush();
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
                //wpcoder110_chatgpt_writelog($feed_name . ' is inactive!');
                $feeds_processed = true; // Mark that feeds were processed.
                continue;
            }

            if (in_array((string) $feed["id"], $processed_feeds)) {
                //wpcoder110_chatgpt_writelog($feed_name . ' is already processes!');
                $lines = explode("<br />", $entry[$field_id]);
                foreach ($lines as $line) {
                    $object = new stdClass();
                    if (empty(trim($line))) {
                        $line = "\n\r";
                    } else {
                        $line = trim($line) . "\n\r";
                    }
                    $object->content = $line;
                    echo "data: " .
                        json_encode(["choices" => [["delta" => $object]]]) .
                        PHP_EOL;
                    echo PHP_EOL;
                    flush();
                }
                echo "data: [DONE]" . PHP_EOL;
                echo PHP_EOL;
                flush();
            } else {
                wpcoder110_chatgpt_writelog(
                    "Processing " . $feed_name . " is active!"
                );

                // All requirements are met; process feed.
                $returned_entry = wpcoder110_make_request($feed, $entry, $form);

                // If returned value from the process feed call is an array containing an id, set the entry to its value.
                if (is_array($returned_entry)) {
                    $entry = $returned_entry;
                    // Adding this feed to the list of processed feeds
                    $processed_feeds[] = $feed["id"];
                } else {
                    //skip
                }
            }
        }

        gform_update_meta($entry["id"], "{$_slug}_is_fulfilled", true);

        // If any feeds were processed, save the processed feed IDs.
        if (!empty($processed_feeds)) {
            // Add this Add-On's processed feeds to the entry meta.
            $meta[$_slug] = $processed_feeds;

            // Update the entry meta.
            gform_update_meta($entry["id"], "processed_feeds", $meta);
        }

        echo "data: [ALLDONE]" . PHP_EOL;
        echo PHP_EOL;
        flush();
        die();
    }
    echo "data: [ALLDONE]" . PHP_EOL;
    echo PHP_EOL;
    flush();
    die();
}

function wpcoder110_chatgpt_writelog($log_text_line)
{
    if (OPAIGFRLT_LOG) {
        $filename = "chatgptlog_" . date("dmY") . ".debug";
        file_put_contents(
            OPAIGFRLT_PATH . "logs/" . $filename,
            $log_text_line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}