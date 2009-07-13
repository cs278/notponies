<?php
/**
* BBCode-safe truncating of text
*
* Originally from {@link http://www.phpbb.com/community/viewtopic.php?f=71&t=670335}
* slightly modified to trim at either the first found end line or space by EXreaction.
*
* Modified by Chris Smith to trim to a specified number of paragraphs and/or a maximum
* number of characters, and provide configurable stopping positions .
*
* @author fberci (http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=158767)
* @author EXreaction (http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=202401)
* @author Chris Smith <toonarmy@phpbb.com> (http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=108642)
* @param string	$text			Text containing BBCode tags to be truncated
* @param string	$uid			BBCode uid
* @param int	$max_length		Text length limit
* @param int	$max_paragraphs	Maximum number of paragraphs permitted
* @param array	$stops			Characters to stop max length search at
* @param string	$replacement	Replacment suffix for the removed text
* @param string	$bitfield		BBCode bitfield (optional)
* @param bool	$enable_bbcode	Whether BBCode is enabled (true by default)
* @return string Resulting trimmed text
*/
function trim_text($text, $uid, $max_length, $max_paragraphs = 0, $stops = array(' ', "\n"), $replacement = '...', $bitfield = '', $enable_bbcode = true)
{
	if ($enable_bbcode)
	{
		static $custom_bbcodes = array();

		// Get all custom bbcodes
		if (empty($custom_bbcodes))
		{
			global $db;

			$sql = 'SELECT bbcode_id, bbcode_tag
				FROM ' . BBCODES_TABLE;
			$result = $db->sql_query($sql, 3600);

			while ($row = $db->sql_fetchrow($result))
			{
				// There can be problems only with tags having an argument
				if (substr($row['bbcode_tag'], -1, 1) == '=')
				{
					$custom_bbcodes[$row['bbcode_id']] = array('[' . $row['bbcode_tag'], ':' . $uid . ']');
				}
			}
			$db->sql_freeresult($result);
		}
	}

	$trimmed = false;

	// Paragraph trimming
	if ($max_paragraphs && $max_paragraphs < preg_match_all('#\n\s*\n#m', $text, $matches))
	{
		$find = $matches[0][$max_paragraphs - 1];
		// Grab all the matches preceeding the paragraph to trim at, finds
		// those that match the trim marker, sum them to skip over them.
		$skip = sizeof(array_intersect(array_slice($matches[0], 0, $max_paragraphs - 1), array($find)));
		$pos = 0;

		do
		{
			$pos = utf8_strpos($text, $find, $pos + 1);
			$skip--;
		}
		while ($skip >= 0);

		$text = utf8_substr($text, 0, $pos);

		$trimmed = true;
	}

	// First truncate the text
	if ($max_length && utf8_strlen($text) > $max_length)
	{
		$pos = 0;
		$length = 0;

		if (!is_array($stops[0]))
		{
			$stops = array($stops);
		}

		foreach ($stops as $stop_group)
		{
			if (!is_array($stop_group))
			{
				continue;
			}

			foreach ($stop_group as $k => $v)
			{
				$find = (is_string($v)) ? $v : $k;
				$include = is_bool($v) && $v;

				if (($_pos = utf8_strpos(utf8_substr($text, $max_length), $find)) !== false)
				{
					if ($_pos < $pos)
					{
						// This is a better find, it cuts the text shorter
						$pos = $_pos;
						$length = $include ? utf8_strlen($find) : 0;
					}
				}
			}

			if ($pos)
			{
				// Include the length of the search string if requested
				$max_length += $pos + $length;
				break;
			}
		}

		$text = utf8_substr($text, 0, $max_length);

		$trimmed = true;
	}

	if ($trimmed)
	{
		$text .= $replacement;
	}

	if (!$enable_bbcode)
	{
		return $text;
	}

	// Some tags may contain spaces inside the tags themselves.
	// If there is any tag that had been started but not ended
	// cut the string off before it begins and add three dots
	// to the end of the text again as this has been just cut off too.
	$unsafe_tags = array(
		array('<', '>'),
		array('[quote=&quot;', "&quot;:$uid]"),
	);

	// If bitfield is given only check for tags that are surely existing in the text
	if (!empty($bitfield))
	{
		// Get all used tags
		$bitfield = new bitfield($bitfield);
		$bbcodes_set = $bitfield->get_all_set();

		// Add custom BBCodes having a parameter and being used
		// to the array of potential tags that can be cut apart.
		foreach ($custom_bbcodes as $bbcode_id => $bbcode_name)
		{
			if (in_array($bbcode_id, $bbcodes_set))
			{
				$unsafe_tags[] = $bbcode_name;
			}
		}
	}
	// Do the check for all possible tags
	else
	{
		$unsafe_tags += $custom_bbcodes;
	}

	foreach ($unsafe_tags as $tag)
	{
		if (($start_pos = strrpos($text, $tag[0])) > strrpos($text, $tag[1]))
		{
			$text = substr($text, 0, $start_pos) . ' ...';
		}
	}

	// Get all of the BBCodes the text contains.
	// If it does not contain any than just skip this step.
	// Preg expression is borrowed from strip_bbcode()
	if (preg_match_all("#\[(\/?)([a-z0-9_\*\+\-]+)(?:=(&quot;.*&quot;|[^\]]*))?(?::[a-z])?(?:\:$uid)\]#", $text, $matches, PREG_PATTERN_ORDER) != 0)
	{
		$open_tags = array();

		for ($i = 0, $size = sizeof($matches[0]); $i < $size; ++$i)
		{
			$bbcode_name = &$matches[2][$i];
			$opening = ($matches[1][$i] == '/') ? false : true;

			// If a new BBCode is opened add it to the array of open BBCodes
			if ($opening)
			{
				$open_tags[] = array(
					'name' => $bbcode_name,
					'plus' => ($opening && $bbcode_name == 'list' && !empty($matches[3][$i])) ? ':o' : '',
				);
			}
			// If a BBCode is closed remove it from the array of open BBCodes.
			// As always only the last opened open tag can be closed
			// we only need to remove the last element of the array.
			else
			{
				array_pop($open_tags);
			}
		}

		// Sort open BBCode tags so the most recently opened will be the first (because it has to be closed first)
		krsort ($open_tags);

		// Close remaining open BBCode tags
		foreach ($open_tags as $tag)
		{
			$text .= '[/' . $tag['name'] . $tag['plus'] . ':' . $uid . ']';
		}
	}

	return $text;
}
