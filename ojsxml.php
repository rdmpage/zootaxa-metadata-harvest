<?php

// Create export for OJS
$issues = null;
$current_volume = '';

//--------------------------------------------------------------------------------------------------
function ojs_initialise ()
{
	global $issues;
	
	$implementation = new DOMImplementation();

	$dtd = $implementation->createDocumentType('issues', '',
		'native.dtd');

	$ojs = $implementation->createDocument('', '', $dtd);
	$ojs->encoding = 'UTF-8';
	$ojs->preserveWhiteSpace = false;
	$ojs->formatOutput = true;
	$issues = $ojs->appendChild($ojs->createElement('issues'));

	return $ojs;
}

//--------------------------------------------------------------------------------------------------
function add_article(&$ojs, &$section, $reference)
{
	$article = $section->appendChild($ojs->createElement('article'));
	$article->setAttribute('language', 'en');
	
	// Identifiers
	if (isset($reference->identifier))
	{
		foreach ($reference->identifier as $identifier)
		{
			switch ($identifier->type)
			{
				case 'zoobank':
					$id = $article->appendChild($ojs->createElement('id'));
					$id->setAttribute('type', $identifier->type);
					$id->appendChild($ojs->createTextNode($identifier->id));
					break;
					
				default:
					break;
			}
		}
	}
	

	$title = $article->appendChild($ojs->createElement('title'));
	$title->setAttribute('locale', 'en_US');
	$title->appendChild($ojs->createTextNode($reference->title));

	$abstract = $article->appendChild($ojs->createElement('abstract'));
	$abstract->setAttribute('locale', 'en_US');
	
	if (isset($reference->abstract))
	{
		$abstract->appendChild($ojs->createTextNode($reference->abstract));
	}

	if (isset($reference->resumo))
	{
		$abstract = $article->appendChild($ojs->createElement('abstract'));
		$abstract->setAttribute('locale', 'pt');
	
		$abstract->appendChild($ojs->createTextNode($reference->resumo));
	}

	if (isset($reference->resumen))
	{
		$abstract = $article->appendChild($ojs->createElement('abstract'));
		$abstract->setAttribute('locale', 'es');
	
		$abstract->appendChild($ojs->createTextNode($reference->resumen));
	}

	$indexing = $article->appendChild($ojs->createElement('indexing'));
	
	$discipline = $indexing->appendChild($ojs->createElement('discipline'));
	$discipline->setAttribute('locale', 'en_US');
	$discipline->appendChild($ojs->createCDATASection(''));			
	
	if (isset($reference->keywords))
	{
		$subject = $indexing->appendChild($ojs->createElement('subject'));
		$subject->setAttribute('locale', 'en_US');
		$subject->appendChild($ojs->createTextNode(join("; ", $reference->keywords)));			

		$subject_class = $indexing->appendChild($ojs->createElement('subject_class'));
		$subject_class->setAttribute('locale', 'en_US');
		$subject_class->appendChild($ojs->createTextNode($reference->keywords[0]));						
	}
	else
	{
		$subject = $indexing->appendChild($ojs->createElement('subject'));
		$subject->setAttribute('locale', 'en_US');
		$subject->appendChild($ojs->createCDATASection(''));			

		$subject_class = $indexing->appendChild($ojs->createElement('subject_class'));
		$subject_class->setAttribute('locale', 'en_US');
		$subject_class->appendChild($ojs->createCDATASection(''));						
	}

	$author_count = 0;
	if (isset($reference->author))
	{
		foreach ($reference->author as $an_author)
		{
			$author = $article->appendChild($ojs->createElement('author'));
		
			if ($author_count == 0)
			{
				$author->setAttribute('primary_contact', 'true');
			}
			else
			{
				$author->setAttribute('primary_contact', 'false');				
			}
		
			$firstname = $author->appendChild($ojs->createElement('firstname'));
			$firstname->appendChild($ojs->createTextNode($an_author->firstname));
		
			if (isset($an_author->middlename))
			{
				$middlename = $author->appendChild($ojs->createElement('middlename'));
				$middlename->appendChild($ojs->createTextNode($an_author->middlename));		
			}

			$lastname = $author->appendChild($ojs->createElement('lastname'));
			$lastname->appendChild($ojs->createTextNode($an_author->lastname));				

			$email = $author->appendChild($ojs->createElement('email'));
			$email->appendChild($ojs->createTextNode('user@example.com'));				
		
			$author_count++;
		}
	}
	
	$pages = $article->appendChild($ojs->createElement('pages'));
	$pages->appendChild($ojs->createTextNode(str_replace('--', '-', $reference->journal->pages)));

	$date_published = $article->appendChild($ojs->createElement('date_published'));
	if (isset($reference->date))
	{
		$date_published->appendChild($ojs->createTextNode($reference->date));
	}
	else
	{
		$date_published->appendChild($ojs->createCDATASection(''));
	}
	
	if ($reference->open_access)
	{
		$article->appendChild($ojs->createElement('open_access'));
	}
	
	
}

//--------------------------------------------------------------------------------------------------
function add_issue(&$ojs, &$issues, &$reference)
{
	$issue = $issues->appendChild($ojs->createElement('issue'));
	$issue->setAttribute('current', 'false');
	$issue->setAttribute('identification', 'title');
	$issue->setAttribute('public_id', '');
	$issue->setAttribute('published', 'true');
	
	// Issue DOI
	$doi = '10.11646/zootaxa.' . $reference->journal->volume . '.1';
	$id = $issue->appendChild($ojs->createElement('id'));
	$id->setAttribute('type', 'doi');
	$id->appendChild($ojs->createTextNode($doi));

	if (isset($reference->date))
	{
		$issue_title = date('j M. Y', strtotime($reference->date));
	}
	else
	{
		$issue_title = 'PublishDate';
	}
	$title = $issue->appendChild($ojs->createElement('title'));
	$title->setAttribute('locale', 'en_US');
	$title->appendChild($ojs->createTextNode($issue_title));
	
	// Volume
	$volume = $issue->appendChild($ojs->createElement('volume'));
	$volume->appendChild($ojs->createTextNode($reference->journal->volume));

	// Pre 2013 this is always 1
	$number = $issue->appendChild($ojs->createElement('number'));
	$number->appendChild($ojs->createTextNode('1'));

	$year = $issue->appendChild($ojs->createElement('year'));
	$year->appendChild($ojs->createTextNode($reference->year));
	
	$date_published = $issue->appendChild($ojs->createElement('date_published'));
	if (isset($reference->date))
	{
		$date_published->appendChild($ojs->createTextNode($reference->date));
	}
	else
	{
		$date_published->appendChild($ojs->createCDATASection(''));
	}				
	
	$section = $issue->appendChild($ojs->createElement('section'));
	$title = $section->appendChild($ojs->createElement('title'));
	$title->setAttribute('locale', 'en_US');
	$title->appendChild($ojs->createTextNode('Articles'));
	
	return $section;
}


?>