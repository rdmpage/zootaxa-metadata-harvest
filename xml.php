<?php

// process pdf2xml output and export Zootaxa metadata

require_once('components.php');
require_once('spatial.php');

//--------------------------------------------------------------------------------------------------
// From http://stackoverflow.com/questions/2312075/get-xpath-of-xml-node-within-recursive-function
function whereami($node)
{
    if ($node instanceof SimpleXMLElement)
    {
        $node = dom_import_simplexml($node);
    }
    elseif (!$node instanceof DOMNode)
    {
        die('Not a node?');
    }

    $q     = new DOMXPath($node->ownerDocument);
    $xpath = '';

    do
    {
        $position = 1 + $q->query('preceding-sibling::*[name()="' . $node->nodeName . '"]', $node)->length;
        $xpath    = '/' . $node->nodeName . '[' . $position . ']' . $xpath;
        $node     = $node->parentNode;
    }
    while (!$node instanceof DOMDocument);

    return $xpath;
}

//--------------------------------------------------------------------------------------------------
// Bounding box
class BBox
{
	var $minx;
	var $maxx;
	var $miny;
	var $maxy;
	
	function __construct($x1=0,$y1=0,$x2=0,$y2=0)
	{
		$this->minx = $x1;
		$this->miny = $y1;
		$this->maxx = $x2;
		$this->maxy = $y2;		
	}
	
	function merge($bbox)
	{
		if (
			($this->minx == 0)
			&& ($this->maxx == 0)
			&& ($this->miny == 0)
			&& ($this->maxy == 0)
			)
		{
			$this->minx = $bbox->minx;
			$this->maxx = $bbox->maxx;
			$this->miny = $bbox->miny;
			$this->maxy = $bbox->maxy;
		}
		else
		{
			$this->minx = min($this->minx, $bbox->minx);
			$this->maxx = max($this->maxx, $bbox->maxx);
			$this->miny = min($this->miny, $bbox->miny);
			$this->maxy = max($this->maxy, $bbox->maxy);
		}
	}
	
	function inflate($x, $y)
	{
		$this->minx -= $x;
		$this->maxx += $x;
		$this->miny -= $x;
		$this->maxy += $x;
	}
	
	function overlap($bbox)
	{
		// P1,3 = minx, miny P1,2 = this, P3,4 = bbox
		// P2,4 = maxx, maxy
		// ! ( P2.y < P3.y || P1.y > P4.y || P2.x < P3.x || P1.x > P4.x )
		if (
			$this->maxy < $bbox->miny
			|| $this->miny > $bbox->maxy
			|| $this->maxx < $bbox->minx
			|| $this->minx > $bbox->maxx
			)
		{
			return false;
		}
		else
		{
			return true;
		}
	}
	
	function toHtml($class = 'block-other')
	{
		$html =
			'<div class="' . $class . '" ' 
			. 'style="position:absolute;'
			. 'left:' . $this->minx . 'px;'
			. 'top:' . $this->miny . 'px;'
			. 'width:' . ($this->maxx - $this->minx) . 'px;'
			. 'height:' . ($this->maxy - $this->miny) . 'px;'
			//. 'border:1px solid black;'
			//. $colour
			. '">'
			. '</div>';
		return $html;
			
	}
	
}


//--------------------------------------------------------------------------------------------------
class Paragraph
{
	var $bbox;
	var $lines = array();

	function __construct()
	{
		$this->bbox = new BBox();
	}
}	

//--------------------------------------------------------------------------------------------------
function find_blocks($page)
{
	// Find blocks by looking for overlap between (inflated)
	// bounding boxes, then find components of graph of overlaps
	$X = array();
	$n = count($page->lines);

	// Create adjacency matrix and fill with 0's
	$X = array();
	for ($i = 0; $i < $n; $i++)
	{
		$X[$i] = array();
		
		for ($j = 0; $j < $n; $j++)
		{ 
			$X[$i][$j] = 0;
		}
	}
	
	// Populate adjacency graph
	foreach ($page->lines as $line)
	{
		$bbox = new BBox();
		$bbox->merge($line->bbox);
		
		// magic_number
		$bbox->inflate(7,7); // to do: need rule for overlap value
		
		$overlap = array();
		foreach ($page->lines as $other_lines)
		{
			if ($other_lines->bbox->overlap($bbox))
			{
				$lines_overlap = true;
				if (1)
				{
					// just accept overlap
				}
				else
				{
					$lines_overlap = false;
					
					// try and develop other rules...
					if ($line->id < $other_lines->id)
					{
						$lines_overlap = $line->bbox->minx  < $other_lines->bbox->minx;
					}
					
					if (!$lines_overlap)
					{
						if ($line->id < $other_lines->id)
						{
							if ($line->bbox->minx == $other_lines->bbox->minx)
							{
								$lines_overlap = $line->bbox->mix > $page->text_bbox->minx;
							}
						}
					}										
				}
				if ($lines_overlap)
				{
					$overlap[] = $other_lines->id;
				}
			}
		}
		
		foreach ($overlap as $o)
		{
			$X[$line->id][$o] = 1;
			$X[$o][$line->id] = 1;
		}
	}
	
	
	// Components of X are blocks of overlapping text
	$blocks = get_components($X);
	
	
	// A block may comprise more than one paragraph or other unit, so see if we can cut the blocks further
	//$blocks = cut($page, $blocks, $X);
	
	// Return partition of text lines into blocks
	return $blocks;

}

//--------------------------------------------------------------------------------------------------
// debugging
function dump_adjacency_matrix($X)
{
	$n = count($X);
	for ($i=0;$i<$n;$i++)
	{
		for ($j=0;$j<$n;$j++)
		{
			echo $X[$i][$j];
		}
		echo "\n";
	}
}


//--------------------------------------------------------------------------------------------------
// $text_block is array of line numbers that belong to that block
// For block of text get details
function get_block_info($page, $text_block)
{
	$block = new stdclass;
	
	// get bounding box for this block
	$block->bbox = new BBox();		
	foreach ($text_block as $i)
	{
		$block->bbox->merge($page->lines[$i]->bbox);
	}
	
	$block->tokens = array();
		
	// text attributes
	$block->font_bold = 0;
	$block->font_italic = 0;
	$block->font_name = array();
	$block->font_size = array();
	
	$block->line_ids = array();
	
	foreach ($text_block as $i)
	{		
		$block->line_ids[] = $i;
	
		// grab block of text
		
		foreach ($page->lines[$i]->tokens as $token)
		{
			$open_style = array();
			$close_style = array();
		
			if ($token->bold)
			{
				$open_style[] = '<b>';
				array_unshift($close_style, '</b>');
				$block->font_bold++;
			}
			if ($token->italic)
			{
				$open_style[] = '<i>';
				array_unshift($close_style, '</i>');
				$block->font_italic++;
			}
		
			$key = strtolower($token->font_name);
			if (!isset($block->font_name[$key]))
			{
				$block->font_name[$key] = 0;
			}
			$block->font_name[$key]++;
			
			
			if (!isset($block->font_size[$token->font_size]))
			{
				$block->font_size[$token->font_size] = 0;
			}
			$block->font_size[$token->font_size]++;
			
			
			$block->tokens[] = join('', $open_style) . $token->text . join('', $close_style) ;
		}
	}
	
	// get most common font size for this block
	// Sort array of font sizes by frequency (highest to lowest)
	arsort($block->font_size, SORT_NUMERIC );
	
	// The font sizes are the keys to the array, so first key is most common font size
	$block->modal_font_size = array_keys($block->font_size)[0];	

	$block->alignment_string = '';
	
	foreach ($text_block as $i)
	{
		// use "ems" as our units
		
		$alignment = 'unknown';
		
		$left = round(($page->lines[$i]->bbox->minx - $block->bbox->minx)/$block->modal_font_size);
		$right =  round(($page->lines[$i]->bbox->maxx - $block->bbox->maxx)/$block->modal_font_size);
		
		if ($left == -0) { $left = 0; }
		if ($right == -0) { $right = 0; }
		
		if (($left == $right) && ($left == 0))
		{
			$alignment = 'j'; // justified
		}
		if (($left == $right) && $left >= 1)
		{
			$alignment = 'c'; // centred
		}
		if ($left >= 1)
		{
			$alignment = 'i'; // indented left
		}
		if (($left == 0) && ($right < 0))
		{
			$alignment = 'g'; // ragged right
		}

		$block->alignment_string .= $alignment;	
		
		classify_block($page, $block);
		
	}

	return $block;
}

//--------------------------------------------------------------------------------------------------
function classify_block($page, &$block)
{
	$block->class = 'block-other';
	
	// Box location rules
	// $page->text_bbox = new BBox($page->width, $page->height, 0, 0);
	
	
	$gap = $page->text_bbox->maxy - $block->bbox->maxy;
	
	if ($gap < 1) // magic_number
	{
		$block->class = 'block-footer';
	}

	if ($block->class == 'block-other')
	{
				
		// Paragraph formatting rules
		if (preg_match('/^ji+g?/', $block->alignment_string))
		{
			$block->class = 'block-hanging';
		}
	
		if (preg_match('/^ij+g?$/', $block->alignment_string))
		{
			$block->class = 'block-indented';
		}
		
		if (preg_match('/^j+g?$/', $block->alignment_string))
		{
			$block->class = 'block-not-indented';
		}
	}	
	
	// Font style rules (e.g., headings)
	if (($block->font_bold > 0) && ($block->font_bold == count($block->tokens)))
	{
		$block->class = 'block-heading';
	
		if ($block->modal_font_size == 13.92)
		{
			$block->class = 'block-title';
		}

	}
}



//--------------------------------------------------------------------------------------------------
// cut blocks based on formatting cues
// For example, use the pattern of margin indentation to locate "significant" blocks of text
function cut($page, $text_blocks, $X)
{
	// create block objects
	foreach ($text_blocks as $text_block)
	{
		// Get information for this block
		$block = get_block_info($page, $text_block);
		
		
		// Journal-specific rules for cutting text
		
		// Zootaxa
		$patterns = array(
			'/(ji{1,3}|g)/U', // references
			'/(ij+g)+/U', // paragraphs
			'/^(j+g)+/U' // first paragraph, or trailing part of paragraph cut by page
			//'/^(j+g)(ij+g)+(ij+)$/U' // 1st paragraph, paragraphs, last one cut by page

		);
		
		$best_pattern = '';
		$best_score = 0;
		
		// Find best rule for cutting this block of text
		foreach ($patterns as $pattern)
		{
			if (preg_match_all($pattern, $block->alignment_string, $m))
			{			
				$score = strlen($m[0][0]);
				if ($score > $best_score)
				{
					$best_score = $score;
					$best_pattern = $pattern;
				}
			}
		}

		// If found a match, cut block		
		if ($best_score != 0)
		{
			if (0)
			{
				echo '<pre>';
				echo $block->alignment_string;
				echo '</pre>';
			}
			if (preg_match_all($best_pattern, $block->alignment_string, $m, PREG_OFFSET_CAPTURE))
			{
				if (0)
				{
					echo '<pre>';
					print_r($m);
					echo '</pre>';
				}
				
				foreach ($m[0] as $hit)
				{
					// start
					$j = $hit[1];
					$i = $j - 1;
					
					if ($i > 0)
					{
						$X[$text_block[$i]][$text_block[$j]] = 0;
						$X[$text_block[$j]][$text_block[$i]] = 0;
					}
					
					/*
					// end (seem to need this if we have repeating patterns)
					$j = $hit[0][1] + strlen($hit[0][0]);
					$i = $j - 1;
					
					if ($j < count($text_block))
					{
						$X[$text_block[$i]][$text_block[$j]] = 0;
						$X[$text_block[$j]][$text_block[$i]] = 0;
					}
					*/
					
				}
			
				// recompute blocks;
				$blocks = get_components($X);
			}
		}
	}
	
				
	
	
	// recompute blocks
	$blocks = get_components($X);
	return $blocks;

}


//--------------------------------------------------------------------------------------------------
// Grab PDF XML and process
function process($filename, &$reference)
{
	$xml = file_get_contents($filename);

	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	$page = new stdclass;
	
	$nodeCollection = $xpath->query ('//PAGE');
	foreach($nodeCollection as $node)
	{
		// coordinates
		if ($node->hasAttributes()) 
		{ 
			$attributes2 = array();
			$attrs = $node->attributes; 
			
			foreach ($attrs as $i => $attr)
			{
				$attributes2[$attr->name] = $attr->value; 
			}
		}
		
		$page->width = $attributes2['width'];
		$page->height = $attributes2['height'];
	}
	
	$page->bbox = new BBox(0, 0, $page->width, $page->height);
	$page->text_bbox = new BBox($page->width, $page->height, 0, 0);
	
	$page->lines = array();
			
	$html = '<div style="position:relative;height:' . $page->height . 'px;">';
	
	if (0)
	{
		// page image from PDF
		$image = $filename;
		$image = str_replace('pageNum', 'image', $image);
		$image = preg_replace('/xml$/', 'png', $image);
		$html .= '<img src="' . $image . '" width="' . $page->width . '">';
	}
		
	// images (figures)
	if (1)
	{
		$images = $xpath->query ('//IMAGE');
		foreach($images as $image)
		{
			// coordinates
			if ($image->hasAttributes()) 
			{ 
				$attributes2 = array();
				$attrs = $image->attributes; 
				
				foreach ($attrs as $i => $attr)
				{
					$attributes2[$attr->name] = $attr->value; 
				}
			}
			$html .= '<div style="position:absolute;' . 'border:1px solid rgba(200, 200, 200, 0.5);' . 'left:' .  $attributes2['x'] . ';'
				. 'top:' . $attributes2['y'] . ';'
				. 'width:' . $attributes2['width'] . ';'
				. 'height:' . $attributes2['height'] . ';">';
			$html .= '<img src="example/' . $attributes2['href'] . '"'
				. ' width="' . $attributes2['width'] . '"'
				. ' height="' . $attributes2['height'] . '"/>';
			$html .= '</div>';
		
		}
	}	
			
	$lines = $xpath->query ('//TEXT');
	$line_counter = 0;
	foreach($lines as $line)
	{
		// coordinates
		if ($line->hasAttributes()) 
		{ 
			$attributes2 = array();
			$attrs = $line->attributes; 
			
			foreach ($attrs as $i => $attr)
			{
				$attributes2[$attr->name] = $attr->value; 
			}
		}
		
		$text = new stdclass;
		
		$text->id = $line_counter++;
		
		$text->bbox = new BBox(
			$attributes2['x'], 
			$attributes2['y'],
			$attributes2['x'] + $attributes2['width'],
			$attributes2['y'] + $attributes2['height']
			);
			
		// Display individual text lines (useful for debugging)
		if (1)
		{
			$html .= $text->bbox->toHtml('block-other');
		}
			
		$page->text_bbox->merge($text->bbox);
		
		// text	
		$text->tokens = array();
	
		$nc = $xpath->query ('TOKEN', $line);
					
		foreach($nc as $n)
		{
			// coordinates
			if ($n->hasAttributes()) 
			{ 
				$attributes2 = array();
				$attrs = $n->attributes; 
				
				foreach ($attrs as $i => $attr)
				{
					$attributes2[$attr->name] = $attr->value; 
				}
			}
			
			$token = new stdclass;
			$token->bold = $attributes2['bold'] == 'yes' ? true : false;
			$token->italic = $attributes2['italic'] == 'yes' ? true : false;
			$token->font_size = $attributes2['font-size'];
			$token->font_name = $attributes2['font-name'];			
			$token->text = $n->firstChild->nodeValue;
			
			$text->tokens[] = $token;				
		}	
	
		$page->lines[] = $text;
		
	}
	
	$html .= $page->bbox->toHtml();
	$html .= $page->text_bbox->toHtml();
	
	
	//----------------------------------------------------------------------------------------------
	// Step 1.
	// Cluster text lines into blocks of text
	// $text_blocks is a list of blocks, each block is a list of line ids that belong to that block
	$text_blocks = find_blocks($page);

	// get block info 
	$blocks = array();
	if (count($text_blocks) > 0)
	{
		foreach ($text_blocks as $k => $text_block)
		{
			$block = get_block_info($page, $text_block);
			
			$block->id = $k;
			
			$blocks[] = $block;

			$html .= $block->bbox->toHtml($block->class);
		}
	}	
	
	
	//----------------------------------------------------------------------------------------------
	// Step 2.

	//----------------------------------------------------------------------------------------------
	// handle image blocks (so we can link to captions)	
	
	
	
	//----------------------------------------------------------------------------------------------
	// Step 3.
	// With remaining blocks, reclassify based on text alignment
	$final_blocks = array();
	if (count($blocks) > 0)
	{
		foreach ($blocks as $old_block)
		{
			$tb = array();
			if (count($old_block->line_ids) == 1)
			{
				$tb[] = $old_block->line_ids;
			}
			else
			{
				// multiple lines, classify
				//print_r($old_block);
			
				// Journal-specific rules for cutting text
				
				// Zootaxa
				$patterns = array(
					'/(ji{1,3}|g)/uU', // references
					'/(ij+g)+/uU', // paragraphs
					'/^(j+g)+/uU' // first paragraph, or trailing part of paragraph cut by page
					//'/^(j+g)(ij+g)+(ij+)$/uU' // 1st paragraph, paragraphs, last one cut by page
		
				);
				
				$best_pattern = '';
				$best_score = 0;
				
				// Find best rule for cutting this block of text
				foreach ($patterns as $pattern)
				{
					if (preg_match_all($pattern, $old_block->alignment_string, $m))
					{	
						if (0)
						{
							echo '<pre>';
							print_r($m);
							echo '</pre>';
						}
						
						$score = 0;
						foreach ($m[0] as $substring)
						{
							$score += strlen($substring);
						}
						if ($score > $best_score)
						{
							$best_score = $score;
							$best_pattern = $pattern;
						}
					}
				}
				
				// hack
				// OK, our code doesn't cut author and address blocks properly, why?
				$best_score = 0;
		
				// If found a match, cut block		
				if ($best_score == 0)
				{
					// no change
					$tb[] = $old_block->line_ids;
				}
				else
				{
					if (0)
					{
						echo '<pre>';
						echo $best_pattern . '<br/>';
						echo $old_block->alignment_string;
						echo '</pre>';
					}
					if (preg_match_all($best_pattern, $old_block->alignment_string, $m, PREG_OFFSET_CAPTURE))
					{
						if (0)
						{
							echo '<pre>';
							print_r($m);
							echo '</pre>';
						}
						
						$last = 0;
						
						foreach ($m[0] as $hit)
						{
							$start = $hit[1];
							$len = strlen($hit[0]); 
							$tb[] = array_slice($old_block->line_ids, $start, $len);
							
							$last += $len;
						}
						$remaining = strlen($old_block->alignment_string) - $last;
						if ($remaining > 0)
						{
							$tb[] = array_slice($old_block->line_ids, $last, $remaining);
						}
					
						// recompute blocks;
						$blocks = get_components($X);
						
						//print_r($tb);
						//exit();
					}
				}
			}
			
			if (0)
			{
				echo '<pre>';
				print_r($tb);
				echo '</pre>';
			}
			foreach ($tb as $bnew)
			{
				$final_blocks[] = get_block_info($page, $bnew);
			}
		}
		
		foreach ($final_blocks as $b)
		{
			$html .= $b->bbox->toHtml($b->class);
		}
	}	
	

	
	//----------------------------------------------------------------------------------------------
	
	// text dump (curiosity)
	$html .= '<div style="position:absolute;left:1000px;top:0px;border:1px solid blue;font-size:10px;">';
	
	foreach ($final_blocks as $block)
	{
		switch ($block->class)
		{
			case 'block-heading':
				$html .= '<h2>' . join(' ', $block->tokens) . '</h2>';
				break;
				
			case 'block-indented':
				$html .= '<p class="indent">' . join(' ', $block->tokens) . '</p>';
				break;
				
			case 'block-hanging':
				$html .= '<p class="hangingindent">' . join(' ', $block->tokens) . '</p>';
				break;
				
			// eat
			case 'block-footer':
				$html .= '<p>' . join(' ', $block->tokens) . '</p>';
				break; 
				
			default:
				$html .=  '<p>';
				$html .=  join(' ', $block->tokens);
				$html .=  '</p>';
				break;
		}
	}
	
	
	$html .= '</div>';
	
	
	
	$html .=  '</div>';
	
	
	
	$html .= '<div style="clear:both;"></div>';
	
	//----------------------------------------------------------------------------------------------
	
	// meta extraction
	$html .= '<div style="position:absolute;left:600px;width:400px;top:0px;border:1px solid blue;font-size:10px;">';
	
	
	
	
	foreach ($final_blocks as $block)
	{
		//print_r($block);
	
		switch ($reference->state)
		{
			case 0:
				// Next block has the abstract
				if (preg_match('/<b>Abstract<\/b>/', $block->tokens[0]))
				{
					$reference->state = 1;
				}
				
				// Key words
				if (preg_match('/<b>Key<\/b>/', $block->tokens[0]))
				{
					// pop off the first two tokens ("Key words")
					$tokens = array_slice($block->tokens, 2);
					$keywords = join(' ', $tokens);
					$keywords = preg_replace('/,(<\/i>)\s+/', '</i>|', $keywords);
					$keywords = preg_replace('/,\s+/', '|', $keywords);
					$keywords = preg_replace('/-\s+/', '', $keywords);
					$reference->keywords = preg_split('/\|/', $keywords);
					$reference->state = 0;
				}
				
				// title
				if ($block->class == "block-title")
				{
					// some early Zootaxa had volume number in big letters
					if (count($block->tokens) > 1)
					{
						if (!isset($reference->title))
						{	
							$tokens = join(' ', $block->tokens);
							$tokens = preg_replace('/(<b>)/', '', $tokens);
							$tokens = preg_replace('/(<\/b>)/', '', $tokens);
							$reference->title = $tokens;
						}
					}
				}
				
				// LSID
				if (preg_match('/<i>Accepted<\/i>/', $block->tokens[0]))
				{
					$text = $block->tokens[count($block->tokens) -  1];
					$text = strip_tags($text);
					if (preg_match('/^urn:lsid:zoobank.org:pub:/', $text))
					{
						$identifier =  new stdclass;
						$identifier->type = 'zoobank';
						$identifier->id = $text;
						
						$reference->identifier[] = $identifier;
					}
					$reference->state = 0;
				}
				
				
				break;
				
			case 1:
				if (!isset($reference->abstract))
				{
					$reference->abstract = '';
				}
				if (in_array($block->class, array("block-not-indented", "block-other")))
				{
					$line = join(' ', $block->tokens);
					$line = preg_replace('/-\s+(\w)/', '$1', $line);
					$line = preg_replace('/-<\/i>\s+<i>/', '', $line);
					$reference->abstract .= $line;
					$reference->state = 0;
				}
				$reference->state = 0;
				break;

			case 2:				
				$reference->state = 0;
				break;
				
			default:
				break;
		}
	
		$reference->blocks[] = $block->tokens[0];
	}

	//$html .= '<pre>' . print_r($reference, true) . '</pre>';	
	if ($reference->page_count == 1) 
	{
		//$html .= '<pre>' . print_r($final_blocks, true) . '</pre>';	
	}
	//$html .= print_r($reference, true);	
	
	$html .= '</div>';

	


	return $html;
}



function extract_metadata(&$reference, $basedir)
{
	// read XML files
	$files = scandir($basedir);

	$xmlfiles = array();

	foreach ($files as $filename)
	{
		if (preg_match('/pageNum-(?<page>\d+)\.xml$/', $filename, $m))
		{	
			$xmlfiles[] = 'pageNum-' . str_pad($m['page'], 3, '0', STR_PAD_LEFT) . '.xml';
		}
	}

	asort($xmlfiles);

	// reference object
	$reference->state = 0;
	$reference->page_count = 0;

	$html = '<html>
	<head>
	<meta charset="utf-8" />
	<link rel="stylesheet" href="style.css" type="text/css" />	
	</head>
	<body style="margin:0px;padding:0px;">';

	foreach ($xmlfiles as $filename)
	{
		if ($reference->page_count == 1)
		{
			$reference->state = 1;
		}
		$filename = preg_replace('/pageNum-[0]+/', 'pageNum-', $filename);
		$html .= process($basedir . '/' . $filename, $reference);
	
		$reference->page_count++;
	}

	$html .= '<pre>' . print_r($reference, true) . '</pre>';	
	//$html .= print_r($reference, true);

	$html .= '<h1>' . $reference->title . '</h1>';	
	$html .= '<p>' . $reference->abstract . '</p>';	
	
	if (isset($reference->keywords))
	{
		$html .= '<p>' . join("; ", $reference->keywords) . '</p>';	
	}

	$html .= '</body>
	<html>';

	//echo $html;
}

// test
if (0)
{
	$reference = new stdclass;
	extract_metadata($reference, 'pdf/2008/z01671p031f.xml_data');
	//extract_metadata($reference, 'pdf/2008/z01673p048f.xml_data');
	print_r($reference);
}



?>