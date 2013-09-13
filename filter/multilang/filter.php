<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    filter
 * @subpackage multilang
 * @copyright  Gaetan Frenoy <gaetan@frenoy.net>
 * @copyright  2004 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Given XML multilinguage text, return relevant text according to
// current language:
//   - look for multilang blocks in the text.
//   - if there exists texts in the currently active language, print them.
//   - else, if there exists texts in the current parent language, print them.
//   - else, print the first language in the text.
// Please note that English texts are not used as default anymore!
//
// This version is based on original multilang filter by Gaetan Frenoy,
// rewritten by Eloy and skodak.
//
// Following new syntax is not compatible with old one:
//   <span lang="XX" class="multilang">one lang</span><span lang="YY" class="multilang">another language</span>
//
// New version by Vanyo Georgiev <info@vanyog.com> 6-April-2013.
// In this version a language block is a serie of identical html tags with lang="XX" atributes
// for different languages. Only the tag for the current language,
// or parent language or the first tag in the series is shown.
// The old syntax with <lang> tags is valid too.

class filter_multilang extends moodle_text_filter {
    public function filter($text, array $options = array()) {
        global $CFG;

        // ...[pj] I don't know about you but I find this new implementation funny :P
        // [skodak] I was laughing while rewriting it ;-)
        // [nicolasconnault] Should support inverted attributes: <span class="multilang" lang="en"> (Doesn't work curently)
        // [skodak] it supports it now, though it is slower - any better idea?

        if (empty($text) or is_numeric($text)) {
            return $text;
        }

        $search = '/<([a-z0-9]+)[^>]*?lang=".*?".*?>.*?<\/\1>\s*(?:<\1[^>]*?lang=".*?".*?>.*?<\/\1>\s*)+/is';

        $result = preg_replace_callback($search, 'filter_multilang_impl', $text);

        if (is_null($result)) {
            return $text; // Error during regex processing (too many nested spans?).
        } else {
            return $result;
        }
    }
}

function filter_multilang_impl($langblock) {
    global $CFG;

    $mylang = current_language();
    static $parentcache;
    if (!isset($parentcache)) {
        $parentcache = array();
    }
    if (!array_key_exists($mylang, $parentcache)) {
        $parentlang = get_parent_language($mylang);
        $parentcache[$mylang] = $parentlang;
    } else {
        $parentlang = $parentcache[$mylang];
    }

    $searchtosplit = '/<(?:'.$langblock[1].')[^>]+lang="([a-zA-Z0-9_-]+)"[^>]*>.*?<\/'.$langblock[1].'>/is';

    if (!preg_match_all($searchtosplit, $langblock[0], $rawlanglist)) {
        // Skip malformed blocks.
        return $langblock[0];
    }

    $langlist = array();
    foreach ($rawlanglist[1] as $index => $lang) {
        $lang = str_replace('-', '_', strtolower($lang)); // Normalize languages.
        $langlist[$lang] = $rawlanglist[0][$index];
    }

    if (array_key_exists($mylang, $langlist)) {
        return $langlist[$mylang];
    } else if (array_key_exists($parentlang, $langlist)) {
        return $langlist[$parentlang];
    } else {
        $first = array_shift($langlist);
        return $first;
    }
}


