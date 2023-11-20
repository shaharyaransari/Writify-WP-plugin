<?php
function add_band_score_merge_tags($merge_tags, $form_id, $fields, $element_id)
{
    foreach ($fields as $field) {
        if (isset($field->id) && !empty($field->label)) {
            $merge_tags[] = array(
                'label' => "Band Score for {$field->label}",
                'tag' => "{band_score_{$field->id}}"
            );
        }
    }
    return $merge_tags;
}

add_filter('gform_custom_merge_tags', 'add_band_score_merge_tags', 10, 4);

function replace_band_score_merge_tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format)
{
    // Check if $form is an array and $form['fields'] is set and is an array
    if (is_array($form) && isset($form['fields']) && is_array($form['fields'])) {
        foreach ($form['fields'] as $field) {
            if (isset($field->id) && strpos($text, "{band_score_{$field->id}}") !== false) {
                $field_value = rgar($entry, $field->id);

                // Extract the first number from the field value
                preg_match('/\d+/', $field_value, $matches);
                $first_number = $matches[0] ?? '';

                $text = str_replace("{band_score_{$field->id}}", $first_number, $text);
            }
        }
    }
    return $text;
}

add_filter('gform_replace_merge_tags', 'replace_band_score_merge_tags', 10, 7);
