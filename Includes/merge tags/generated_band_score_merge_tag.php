<?php
add_filter('gform_custom_merge_tags', 'add_generated_band_score_merge_tags', 10, 4);
function add_generated_band_score_merge_tags($merge_tags, $form_id, $fields, $element_id)
{
    $criteria = ['TR', 'CC', 'LR', 'GRA'];

    foreach ($criteria as $criterion) {
        $merge_tags[] = array(
            'label' => "Generated $criterion Band Score",
            'tag' => "{generated_{$criterion}_band_score_[field_id]}"
        );
    }

    return $merge_tags;
}
add_filter('gform_replace_merge_tags', 'replace_generated_band_score_merge_tags', 10, 7);
function replace_generated_band_score_merge_tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format)
{
    $criteria = ['TR', 'CC', 'LR', 'GRA'];

    foreach ($criteria as $criterion) {
        $custom_tag = "/{generated_{$criterion}_band_score_(\d+)}/";
        preg_match_all($custom_tag, $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $field_id = $match[1];
            $field_value = rgar($entry, $field_id);
            $parsedData = parse_field_value($field_value);
            $bandDescriptors = get_band_descriptors($criterion);
            $band_score = get_lowest_band_score($parsedData, $bandDescriptors);
            $text = str_replace($match[0], $band_score, $text);
        }
    }

    return $text;
}


function parse_field_value($field_value)
{
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


function get_band_descriptors($type)
{
    $descriptors = [
        'TR' => [
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

        ],
        'CC' => [
            8 => [
                'Easy to follow, logically sequenced, well-managed cohesion with minor lapses.',
                'Used sufficiently and appropriately, with logical idea sequencing.',
                'Skillfully used with occasional lapses.',
            ],
            7 => [
                'Generally logically organized, clear progression throughout.',
                'Generally effective, with mostly logical idea sequencing.',
                'Flexible use with some inaccuracies or inappropriate amounts.'
            ],
            6 => [
                'Mostly coherent arrangement of ideas, clear overall structure.',
                'Sometimes illogical, central topic may be unclear.',
                'Lacks flexibility, causing repetition and errors.'
            ],
            5 => [
                'Not wholly logical organization, lacks overall progression.',
                'Inadequately used or missing, unclear main topic.',
                'Main ideas are present but limited or not well-developed.',
            ],
            4 => [
                'Not arranged coherently, no clear progression.',
                'No paragraphing, no clear main topic.',
                'Inaccurate, lacks substitution and referencing.'
            ]

        ],
        'LR' => [
            8 => [
                'Uses a broad vocabulary fluently and flexibly for precise meaning.',
                'Skillfully uses uncommon or idiomatic language, despite occasional inaccuracies.',
                'Occasional errors, minimal impact on communication.',
            ],
            7 => [
                'Sufficient vocabulary for some flexibility and precision.',
                'Shows awareness of style, though with some inappropriate choices.',
                'Few errors, do not detract from overall clarity.'
            ],
            6 => [
                'Generally adequate vocabulary for the task.',
                'Generally clear meaning despite limited range or lack of precision.',
                'Some errors, but do not impede communication.'
            ],
            5 => [
                'Limited but minimally adequate vocabulary for the task.',
                'Simple vocabulary used accurately, but lacks variation. May have frequent inappropriate choices.',
                'Noticeable errors, may cause difficulty for the reader.',
            ],
            4 => [
                'Very limited and inadequate vocabulary, basic and repetitive.',
                'Inappropriate use of memorized phrases or formulaic language.',
                'Errors may impede meaning.'
            ]

        ],
        'GRA' => [
            8 => [
                'Wide range used flexibly and accurately.',
                'Mostly error-free sentences with occasional, minor errors.',
                'Well-managed punctuation.',
            ],
            7 => [
                'Variety of complex structures used with some flexibility and accuracy.',
                'Many error-free sentences; a few errors that do not impede communication.',
                'Generally well-controlled punctuation.'
            ],
            6 => [
                'Mix of simple and complex forms, with limited flexibility. Less accuracy in complex structures.',
                'Errors occur but rarely hinder communication.',
                'Some errors in punctuation.'
            ],
            5 => [
                'Limited range, repetitive. Complex sentences attempted but often faulty.',
                'Frequent errors that may cause difficulty for the reader.',
                'Faulty punctuation may be noticeable.',
            ],
            4 => [
                'Very limited range, mostly simple sentences, rare use of subordinate clauses.',
                'Some accurate structures, but frequent errors that may impede meaning.',
                'Often faulty or inadequate punctuation.'
            ]

        ],

    ];
}

function get_lowest_band_score($parsedData, $type)
{
    $bandDescriptors = get_band_descriptors($type);
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

add_filter('gform_custom_merge_tags', 'add_bullet_points_with_scores_merge_tag', 10, 4);
function add_bullet_points_with_scores_merge_tag($merge_tags, $form_id, $fields, $element_id)
{
    $criteria = ['TR', 'CC', 'LR', 'GRA'];

    foreach ($criteria as $criterion) {
        $merge_tags[] = array(
            'label' => "Bullet Points with Scores for $criterion",
            'tag' => "{bullet_points_with_scores_{$criterion}_[field_id]}"
        );
    }

    return $merge_tags;
}

add_filter('gform_replace_merge_tags', 'replace_bullet_points_with_scores_merge_tag', 10, 7);
function replace_bullet_points_with_scores_merge_tag($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format)
{
    $criteria = ['TR', 'CC', 'LR', 'GRA'];

    foreach ($criteria as $criterion) {
        $custom_tag = "/{bullet_points_with_scores_{$criterion}_(\d+)}/";
        preg_match_all($custom_tag, $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $field_id = $match[1];
            $field_value = rgar($entry, $field_id);

            // Process the field value to append scores to bullet points
            $processed_value = process_field_for_scores($field_value, $criterion);

            $text = str_replace($match[0], $processed_value, $text);
        }
    }

    return $text;
}


function process_field_for_scores($field_value, $type)
{
    $normalized_value = str_replace(array("\r\n", "\r"), "\n", $field_value);
    $lines = explode("\n", $normalized_value);
    $processedText = '';

    foreach ($lines as $line) {
        $startPos = strpos($line, '"');
        $endPos = strrpos($line, '"');

        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            $extractedText = substr($line, $startPos + 1, $endPos - $startPos - 1);
            $bandScore = get_band_score_for_text($extractedText, $type);
            if (!empty($bandScore)) {
                $processedText .= $line . ' - Characteristic of Band **' . $bandScore . "**\n";
            } else {
                $processedText .= $line . "\n";
            }
        }
    }

    return $processedText;
}

function get_band_score_for_text($text, $type)
{
    $bandDescriptors = get_band_descriptors($type);

    foreach ($bandDescriptors as $band => $descriptors) {
        if (in_array($text, $descriptors)) {
            return $band;
        }
    }
    return ''; // Default return if no match is found
}