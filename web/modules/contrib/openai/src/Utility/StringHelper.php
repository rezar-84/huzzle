<?php

namespace Drupal\openai\Utility;

use Drupal\Component\Utility\Unicode;

/**
 * A utility class for preparing strings when using OpenAI endpoints.
 *
 * @group openai
 */
class StringHelper {

  /**
   * Prepares text for prompt inputs.
   *
   * OpenAIs completion endpoint or any other prompt input API
   * performs worse with strings that contain HTML, certain
   * punctuations, whitespace, and newlines.
   *
   * This method will clean up a string before sending it to OpenAI.
   *
   * @param string $text
   *   The text to attach to a prompt.
   * @param array $removeHtmlElements
   *   An array of HTML elements to remove.
   * @param int $max_length
   *   The maximum length of the text to return. A lower limit
   *   will result in faster response from OpenAI and reduce
   *   API usage. A helpful rule of thumb is that one token generally
   *   corresponds to ~4 characters of text for common English text.
   *   This translates to roughly ¾ of a word (so 100 tokens ~= 75 words).
   *
   * @return string
   *   The prepared text.
   */
  public static function prepareText(string $text, array $removeHtmlElements = [], int $max_length = 10000): string {
    // Never include the contents of the following tags.
    $removeHtmlElements += ['pre', 'code', 'script', 'iframe'];

    // Ensure we have a root element since LIBXML_HTML_NOIMPLIED is being used.
    // @see https://stackoverflow.com/questions/29493678/loadhtml-libxml-html-noimplied-on-an-html-fragment-generates-incorrect-tags
    $text = '<div>' . $text . '</div>';

    $dom = new \DOMDocument('5.0', 'utf-8');
    $dom->formatOutput = FALSE;
    $dom->preserveWhiteSpace = TRUE;
    $dom->loadHTML(mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS);
    $removeElements = [];

    // Collect a list of DOM nodes we want to remove.
    foreach ($removeHtmlElements as $htmlElement) {
      $tags = $dom->GetElementsByTagName($htmlElement);

      foreach ($tags as $tag) {
        $removeElements[] = $tag;
      }
    }

    // Delete the DOM nodes.
    foreach ($removeElements as $removeElement) {
      $removeElement->parentNode->removeChild($removeElement);
    }

    $text = $dom->saveHTML();
    $text = html_entity_decode($text);
    $text = strip_tags(trim($text));
    $text = str_replace(["\r\n", "\r", "\n", "\\r", "\\n", "\\r\\n"], "", $text);
    $text = trim($text);
    $text = preg_replace("/  +/", ' ', $text);
    $text = preg_replace("/[^a-z0-9.?!,' ]/i", '', $text);
    // @todo Here is where we could remove stopwords
    return Unicode::truncate($text, $max_length, TRUE);
  }

}
