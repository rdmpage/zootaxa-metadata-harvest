<?php


// Date timezone
date_default_timezone_set('UTC');

require_once(dirname(__FILE__) . '/lib.php');
require_once(dirname(__FILE__) . '/nameparse.php');
require_once(dirname(__FILE__) . '/ojsxml.php');
require_once(dirname(__FILE__) . '/xml.php');

global $issues;
global $current_volume;

stream_context_set_default(
    array(
        'http' => array(
            'method' => 'HEAD'
        )
    )
);

//--------------------------------------------------------------------------------------------------
function authors_from_string($authorstring)
{
	$authors = array();
	
	// Strip out suffix
	$authorstring = preg_replace("/,\s*Jr./u", "", trim($authorstring));
	$authorstring = preg_replace("/,\s*jr./u", "", trim($authorstring));
	
	$authorstring = preg_replace("/,/u", "|", trim($authorstring));



	$authorstring = preg_replace("/,$/u", "", trim($authorstring));
	$authorstring = preg_replace("/&/u", "|", $authorstring);
	$authorstring = preg_replace("/;/u", "|", $authorstring);
	$authorstring = preg_replace("/ and /u", "|", $authorstring);
	$authorstring = preg_replace("/\.,/Uu", "|", $authorstring);				
	$authorstring = preg_replace("/\|\s*\|/Uu", "|", $authorstring);				
	$authorstring = preg_replace("/\|\s*/Uu", "|", $authorstring);				
	$authors = explode("|", $authorstring);
	
	for ($i = 0; $i < count($authors); $i++)
	{
		$authors[$i] = preg_replace('/\.([A-Z])/u', ". $1", $authors[$i]);
		$authors[$i] = preg_replace('/^\s+/u', "", $authors[$i]);
		$authors[$i] = mb_convert_case($authors[$i], MB_CASE_TITLE, 'UTF-8');
	}

	return $authors;
}






$basedir = 'zootaxa/list';
//$basedir = 'zootaxa/list/2008';
//$basedir = 'zootaxa/list/2012';

$files1 = scandir($basedir);

//print_r($files1);

$count = 0;

foreach ($files1 as $filename)
{
	if (preg_match('/\.html$/', $filename))
	{	
		echo $filename . "\n";
	
		$ojs = ojs_initialise();
		$base_filename = str_replace('.html', '', $filename);
	
	
		$html = file_get_contents($basedir . '/' . $filename);
		
		$html = str_replace("–", "-", $html);
		$html = str_replace("&nbsp;", " ", $html);

		
		$html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');

		$html = str_replace("\n", " ", $html);
		
		$html = preg_replace('/<p(\s+align="left")?>/', '<PARAGRAPH>', $html);
		$paragraphs = explode('<PARAGRAPH>', $html);
		
		//print_r($paragraphs);
		//exit();
		
		foreach($paragraphs as $paragraph)
		{
			if (preg_match('/^<font( color="#FFFFFF" face="Times-Bold")? size="2"/', $paragraph))
			{
				$rows = explode("<br>", $paragraph);
				
				//print_r($rows);
				
				
				switch ($filename)
				{
					case 'list2002.html':
						if (preg_match('/Monograph/', $rows[0]))
						{
							$title_row = 2;
							$author_row = 3;						
							$metadata_row = 1;
							$link_row = 4;						
						}
						else
						{
							$title_row = 1;
							$author_row = 2;						
							$metadata_row = 0;
							$link_row = 3;
						}
						break;
				
					case 'list2001.html':
					default:
						$title_row = 2;
						$author_row = 3;						
						$metadata_row = 1;
						$link_row = 4;
						break;
				}
				
				
				$reference = new stdclass;
				$reference->type = 'article';
				
				
				// details
				$reference->journal  = new stdclass;
				$reference->journal->name = 'Zootaxa';
				$reference->journal->identifier = array();
				$identifier = new stdclass;
				$identifier->type = 'issn';
				$identifier->id = '1175-5326';
				$reference->journal->identifier[] = $identifier;	
				
				// title
				$reference->title = $rows[$title_row];
				
				// unwanted tags
				$reference->title = preg_replace("/<b>/i", "", $reference->title);
				$reference->title = preg_replace("/<\/b>/i", "", $reference->title);
				$reference->title = preg_replace("/^<\/i>/i", "", $reference->title);
				
				// stray junk
				$reference->title = preg_replace('/^\s*<\/i>/u', ' ', $reference->title);

				$reference->title = preg_replace("/<font\s*(.*)>/Uui", "", $reference->title);
				$reference->title = preg_replace("/<\/font>/i", "", $reference->title);

				// Erata have links
				$reference->title = preg_replace("/<a\s*(.*)>/Uui", "", $reference->title);
				$reference->title = preg_replace("/<\/a>/i", "", $reference->title);
				
				
				$reference->title = str_replace("\n", " ", $reference->title);
				$reference->title = str_replace("\r", "", $reference->title);
				
				$reference->title = html_entity_decode($reference->title, ENT_COMPAT, 'UTF-8');
				
				// remove extra whitespace
				$reference->title = preg_replace('/\s\s+/u', ' ', $reference->title);
				$reference->title = preg_replace('/^\s+/u', '', $reference->title);
				
				// is this an erratum?
				if (preg_match('/^Erratum/', $reference->title))
				{
					// no authors
					$author_row = -1;
				}
				
				// authors
				if ($author_row != -1)
				{
					$authorstring = trim(strip_tags($rows[$author_row]));
					$authorstring = preg_replace('/\((.*)\)/U', "", $authorstring);
					$authorstring = preg_replace('/&amp;/U', "&", $authorstring);
					$authorstring = preg_replace('/\.([A-Z])/U', ". $1", $authorstring);
					$authorstring = mb_convert_case($authorstring, MB_CASE_TITLE, 'UTF-8');
					$authorstring = html_entity_decode($authorstring, ENT_COMPAT, 'UTF-8');
		
					$authorstring = preg_replace('/\s*,\s*/U', "|", $authorstring);
					$authorstring = preg_replace('/\s*&\s*/U', "|", $authorstring);
				
					// parse properly
					$authors = authors_from_string($authorstring);
				
					$reference->author = array();
					foreach ($authors as $a)
					{
						// Get parts of name
						$parts = parse_name($a);
	
						$author = new stdClass();
	
						if (isset($parts['last']))
						{
							$author->lastname = $parts['last'];
						}
						if (isset($parts['suffix']))
						{
							$author->suffix = $parts['suffix'];
						}
						if (isset($parts['first']))
						{
							$author->firstname = $parts['first'];
		
							if (array_key_exists('middle', $parts))
							{
								$author->middlename = $parts['middle'];
							}
						}
						$author->name = $a;
					
						$reference->author[] = $author;
					}
				}				
				
				// metadata
				if (preg_match('/<b>(?<volume>\d+)<\/b>:\s+(?<spage>\d+)(.(?<epage>\d+))?\s+\((?<date>(.*) (?<year>[0-9]{4}))<\/i>\)/U', $rows[$metadata_row], $mm))
				{
					//print_r($mm);
					
					$reference->journal->volume = $mm['volume'];
					$reference->journal->spage = $mm['spage'];
					$reference->journal->epage = $mm['epage'];
					$reference->journal->pages = $reference->journal->spage;
					
					if ($reference->journal->epage != '')
					{
						 $reference->journal->pages .= '--' . $reference->journal->epage ;
					}
					
					$reference->year = $mm['year'];
					
					$reference->date = $mm['date'];
					$reference->date = strip_tags($reference->date);
					$reference->date = str_replace('.', '', $reference->date);
					$reference->date = preg_replace('/\s\s+/', ' ', $reference->date);
					
					$reference->date = date('Y-m-d', strtotime($reference->date));
				
								
				}
				
				// URL and PDF
				if (preg_match('/<a href="(?<url>.*)\.pdf">Abstract /Uu', $rows[$link_row], $mm))
				{
					$reference->url = $mm['url'] . '.pdf';
				}
				// 2001
				if (preg_match('/font>\s+<a href="(?<url>.*)\.pdf">Full/', $rows[$link_row], $mm))
				{
					$reference->pdf = $mm['url'] . '.pdf';
				}
				// 2002
				if (preg_match('/\|\s+<\/font><a href="(?<url>.*)\.pdf">Full/', $rows[$link_row], $mm))
				{
					$reference->pdf = $mm['url'] . '.pdf';
				}
				
				// Open Access?
				$reference->open_access = false;
				if (isset($reference->pdf))
				{
					$headers = get_headers($reference->pdf);	
					print_r($headers);
					
					if ($headers[0] == 'HTTP/1.1 200 OK')
					{
						$reference->open_access = true;
					}
				}
				
				// get more info from Abstract PDF
				if (1)
				{
					echo $reference->url . "\n";
				
				
					$pdf_dir = dirname(__FILE__) . '/pdf';
	
					if (preg_match('/(?<year>[0-9]{4})(\/)?f\/(?<name>.*)\.pdf/', $reference->url, $m))
					{
						$year = $m['year'];
						$name = $m['name'];
		
						$dir = $pdf_dir . '/' . $year;
						if (!file_exists($dir))
						{
							$oldumask = umask(0); 
							mkdir($dir, 0777);
							umask($oldumask);
						}
		
						$f = $dir . '/' . $name . '.pdf';
						
						$have_pdf = false;
					
						if (!file_exists($f))
						{
							$pdf = get($reference->url);
							
							if ($pdf != '')
							{
								$file = fopen($f, "w");
								fwrite($file, $pdf);
								fclose($file);
								
								$have_pdf = true;
							}
						}
						else
						{
							$have_pdf = true;
						}
		
						if ($have_pdf)
						{
							// generate XML
							$command = "/Users/rpage/Development/pdf2xml/pdftoxml -cutPages $f";
							//echo $command . "\n";
		
							system($command);
					
							$xmldir = $dir . '/' . $name . '.xml_data';
					
							extract_metadata($reference, $xmldir);
						}
					}
				}				
				
				
				
				if (isset($reference->journal->issue) && !isset($reference->journal->volume))
				{
					$reference->journal->volume = $reference->journal->issue;
					unset ($reference->journal->issue);
				}
				
				//print_r($reference);
				
	
				// OJS xml
				if ($reference->journal->volume != $current_volume)
				{
					$section = add_issue($ojs, $issues, $reference);
					$current_volume = $reference->journal->volume;
				}
	
	
				add_article($ojs, $section, $reference);
				
				$count++;
				//if ($count == 20) exit();
				
				
			}
		}

		$ojs_filename = dirname(__FILE__) . '/ojs/' . $base_filename . '.xml';
		$xml = $ojs->saveXML();
		file_put_contents($ojs_filename, $xml);
		
		// transform
		$xp = new XsltProcessor();
		$xsl = new DomDocument;
		$xsl->load(dirname(__FILE__) . '/display.xsl');
		$xp->importStylesheet($xsl);
		
		$xml_doc = new DOMDocument;
		$xml_doc->loadXML($xml);
		
		$output_filename = dirname(__FILE__) . '/ojs/' . $base_filename . '.html';
		$output = $xp->transformToXML($xml_doc);
		
		file_put_contents($output_filename, $output);
		
		
		
	}
}



?>