<?php
add_filter('gform_custom_merge_tags', 'add_generated_TR_band_score_merge_tag', 10, 4);
function add_generated_TR_band_score_merge_tag($merge_tags, $form_id, $fields, $element_id)
{
    $merge_tags[] = array('label' => 'Generated Task Response Band Score', 'tag' => '{generated_TR_band_score_[field_id]}');
    return $merge_tags;
}

add_filter( 'gform_replace_merge_tags', 'replace_generated_TR_band_score_merge_tag', 10, 7 );
function replace_generated_TR_band_score_merge_tag( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
    $custom_tag = '/{generated_TR_band_score_(\d+)}/';
    preg_match_all( $custom_tag, $text, $matches, PREG_SET_ORDER );

    foreach ( $matches as $match ) {
        $field_id = $match[1];
        $field_value = rgar( $entry, $field_id );
        error_log("Processing field ID: $field_id with value: $field_value");

        $parsedData = parse_field_value( $field_value );
        error_log('Parsed data: ' . print_r($parsedData, true));

        $bandDescriptors = get_band_descriptors();
        $band_score = get_lowest_band_score($parsedData, $bandDescriptors);

        error_log("Calculated band score: $band_score for field ID: $field_id");
        $text = str_replace( $match[0], $band_score, $text );
    }

    return $text;
}

function parse_field_value( $field_value ) {
    // Normalize line breaks to a single type (\n)
    $normalized_value = str_replace(array("\r\n", "\r"), "\n", $field_value);

    // Split the normalized value into lines
    $lines = explode("\n", $normalized_value);
    $parsedData = [];

    foreach ($lines as $line) {
        // Manually extract text within quotes
        $startPos = strpos($line, '"');
        $endPos = strrpos($line, '"');

        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            $extractedText = substr($line, $startPos + 1, $endPos - $startPos - 1);
            $parsedData[] = $extractedText;
        }
    }

    error_log('Bullets extracted: ' . print_r($parsedData, true));
    return $parsedData;
}


function get_band_descriptors()
{
    return [
        8 => [
            'Fully and appropriately addresses the prompt.',
            'Position is clear, well-developed, and consistent.',
            'Ideas are relevant, extended, and well-supported.',
            'Occasional omissions or lapses in content.'
        ],
        7 => [
            'Addresses the main parts of the prompt.',
            'Position is clear and developed.',
            'Ideas extended and supported but may lack focus.'
        ],
        6 => [
            'Addresses the main parts, though some more than others, with appropriate format.',
            'Position is relevant but may lack clear conclusions.',
            'Main ideas are relevant but underdeveloped or unclear.'
        ],
        5 => [
            'Incompletely addresses main parts, sometimes with inappropriate format.',
            'Position expressed but not always clear.',
            'Main ideas are present but limited or not well-developed.',
        ],
        4 => [
            'Minimal or tangential response, possibly due to misunderstanding, format may be inappropriate.',
            'Position discernible but not evident.',
            'Main ideas difficult to identify, lacking relevance or support.',
            'Off topic or tangentially related.'
        ]
    ];
}

function get_lowest_band_score($parsedData, $bandDescriptors)
{
    $lowestBand = max(array_keys($bandDescriptors)); // Start with the highest band

    foreach ($parsedData as $value) {
        foreach ($bandDescriptors as $band => $descriptors) {
            if (in_array($value, $descriptors)) {
                $lowestBand = min($lowestBand, $band);
                break; // Stop checking once a match is found for this bullet point
            }
        }
    }

    return $lowestBand;
}

add_filter( 'gform_custom_merge_tags', 'add_bullet_points_with_scores_merge_tag', 10, 4 );
function add_bullet_points_with_scores_merge_tag( $merge_tags, $form_id, $fields, $element_id ) {
    // Add new merge tag for bullet points with scores
    $merge_tags[] = array( 'label' => 'Bullet Points with Scores', 'tag' => '{bullet_points_with_scores_[field_id]}' );

    return $merge_tags;
}


add_filter( 'gform_replace_merge_tags', 'replace_bullet_points_with_scores_merge_tag', 10, 7 );
function replace_bullet_points_with_scores_merge_tag( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
    $custom_tag = '/{bullet_points_with_scores_(\d+)}/';
    preg_match_all( $custom_tag, $text, $matches, PREG_SET_ORDER );

    foreach ( $matches as $match ) {
        $field_id = $match[1];
        $field_value = rgar( $entry, $field_id );

        // Process the field value to append scores to bullet points
        $processed_value = process_field_for_scores( $field_value );

        $text = str_replace( $match[0], $processed_value, $text );
    }

    return $text;
}

function process_field_for_scores( $field_value ) {
    $normalized_value = str_replace(array("\r\n", "\r"), "\n", $field_value);
    $lines = explode("\n", $normalized_value);
    $bandDescriptors = get_band_descriptors();
    $processedText = '';

    foreach ($lines as $line) {
        $startPos = strpos($line, '"');
        $endPos = strrpos($line, '"');

        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            $extractedText = substr($line, $startPos + 1, $endPos - $startPos - 1);
            $bandScore = get_band_score_for_text($extractedText, $bandDescriptors);
            // Append "Characteristic of Band" only if bandScore is not empty
            if (!empty($bandScore)) {
                $processedText .= $line . ' - Characteristic of Band ' . $bandScore . "\n";
            } else {
                $processedText .= $line . "\n";
            }
        }
    }

    return $processedText;
}

function get_band_score_for_text($text, $bandDescriptors) {
    foreach ($bandDescriptors as $band => $descriptors) {
        if (in_array($text, $descriptors)) {
            return $band;
        }
    }
    return ''; // Default return if no match is found
}
