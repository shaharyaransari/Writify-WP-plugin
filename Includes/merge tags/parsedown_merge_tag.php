<?php
add_filter('gform_custom_merge_tags', 'add_parsedown_merge_tags', 10, 4);
function add_parsedown_merge_tags($merge_tags, $form_id, $fields, $element_id)
{
    foreach ($fields as $field) {
        if (isset($field->id) && !empty($field->label)) {
            $merge_tags[] = array(
                'label' => "Parsedown: " . $field->label,
                'tag' => "{parsedown_field_{$field->id}}"
            );
        }
    }
    return $merge_tags;
}


add_filter('gform_replace_merge_tags', 'replace_parsedown_merge_tags', 10, 7);
function replace_parsedown_merge_tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format)
{
    // Check if $form is an array and $form['fields'] is set and is an array
    if (is_array($form) && isset($form['fields']) && is_array($form['fields'])) {
        foreach ($form['fields'] as $field) {
            if (isset($field->id) && strpos($text, "{parsedown_field_{$field->id}}") !== false) {
                $field_value = rgar($entry, $field->id);

                // Process with Parsedown
                $Parsedown = new Parsedown();
                $html = $Parsedown->text($field_value);

                $text = str_replace("{parsedown_field_{$field->id}}", $html, $text);
            }
        }
    }
    return $text;
}