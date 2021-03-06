<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use DaveChild\TextStatistics as TS;
$textStatistics = new TS\TextStatistics;

// this could be made into e.g. calling the API's siprop=languages
$languages = array( 'en', 'de', 'fr', 'nl' );

$cat = isset( $_GET['wpcat'] ) ? $_GET['wpcat'] : '';
$lang = isset( $_GET['wplang'] ) && in_array( $_GET['wplang'], $languages ) ? $_GET['wplang'] : 'en';
$limit = 20;

function executeTool( $wpCat, $wpLang, $wpLimit ) {
	global $textStatistics;

	$wpDomain = $wpLang . '.wikipedia.org';
	$wpApi = 'https://' . $wpDomain . '/w/api.php';

	$wpApiQueryParams = array(
		'action' => 'query',
		'format' => 'json',
		'list' => 'categorymembers',
		'cmtitle' => 'Category:' . $wpCat,
		// need to take a high number because non-articles are filtered out
		'cmlimit' => 500,
		// sort by most recent
		'cmsort' => 'timestamp',
		// unfortunately doesn't work together with cmsort=timestamp
		// 'cmtype' => 'page',
	);
	$wpApiQuery = $wpApi . '?' . http_build_query( $wpApiQueryParams );
	$wpCatArticles = file_get_contents( $wpApiQuery );
	$wpCatArticles = json_decode( $wpCatArticles, true );

	if( isset( $wpCatArticles['error'] ) ) {
		echo '<p>There is an error!</p>';
		return;
	}

	$wpCatArticles = $wpCatArticles['query']['categorymembers'];

	$wpCatLink = '<a href="https://' . $wpDomain . '/wiki/Category:' .
		htmlspecialchars( $wpCat ) . '" title="Category:' . $wpCat . '">' . $wpCat . '</a>';

	if( count( $wpCatArticles ) < 1 ) {
		echo '<p>Sorry, we did not find articles in the category "' . $wpCatLink .
			'" on ' . $wpDomain . '! The category probably does not exist.</p>';
		return;
	}

	echo '<p>Below is a list of the ' . $wpLimit . ' most recently added articles to the category "' .
		$wpCatLink . '" on ' . $wpDomain . '.</p>';
	echo '<p>It is scored by readability based on the ' .
		'<a href="https://en.wikipedia.org/wiki/Flesch%E2%80%93Kincaid_readability_tests">Flesch–Kincaid</a> ' .
		'reading ease (least readable first).</p>'."\n";
	echo '<ol>'."\n";
	$wpCatArticles2 = array();
	$wpCatArticlesScored = array();
	foreach( $wpCatArticles as $wpCatArticle ) {
		if( $wpCatArticle['ns'] === 0 ) {
			// only if it is an actual article, not e.g. a template
			$wpCatArticles2[$wpCatArticle['pageid']] = $wpCatArticle['title'];
		}
	}
	$wpCatArticles2 = array_slice( $wpCatArticles2, 0, $wpLimit );
	// at this point we have an array $wpCatArticles2 with pageid => pagetitle
	// now do an API request to get content extracts
	$wpApiQueryParams2 = array(
		'action' => 'query',
		'format' => 'json',
		'prop' => 'extracts',
		'titles' => implode( '|' , $wpCatArticles2 ),
		'exchars' => 600,
		'exlimit' => $wpLimit,
	);
	$wpApiQuery = $wpApi . '?explaintext&exintro&' . http_build_query( $wpApiQueryParams2 );
	$wpCatArticlesContent = file_get_contents( $wpApiQuery );
	$wpCatArticlesContent = json_decode( $wpCatArticlesContent, true );
	$wpCatArticlesContent = $wpCatArticlesContent['query']['pages'];
	foreach( $wpCatArticlesContent as $wpCatArticleContentId => $wpCatArticleContent ) {
		// get a readability score based on the article's intro
		$score = $textStatistics->fleschKincaidReadingEase( $wpCatArticleContent['extract'] );
		$wpCatArticlesScored[$wpCatArticleContent['title']] = $score;
	}

	// sort low number (low readability) to high number (high readability)
	asort( $wpCatArticlesScored );

	foreach( $wpCatArticlesScored as $wpCatArticleScored => $wpCatArticleScore ) {
		$wpCatUrl = 'https://' . $wpDomain . '/wiki/' . str_replace( ' ', '_', $wpCatArticleScored );
		echo '<li><a href="' . $wpCatUrl . '" title="' . $wpCatArticleScored . '">' .
			$wpCatArticleScored . '</a> (score: ' . $wpCatArticleScore . ')</li>'."\n";
	}
	echo '</ol>'."\n";
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Wikipedia category scoring</title>
<meta charset="UTF-8"/>
</head>
<body>
<fieldset>
<legend>Wikipedia category scoring</legend>
<form method="get" name="wpcatscoring">
<p>
<label for="wpcat">Category:</label>
<input type="text" id="wpcat" name="wpcat" value="<?php echo htmlspecialchars( $cat ); ?>" />
<label for="wplang">&nbsp;on&nbsp;</label>
<input type="text" size="2" id="wplang" name="wplang" value="<?php echo $lang; ?>" style="text-align:center;" />
<label for="wplang">.wikipedia.org</label>
</p>
<p><input type="submit" value="Go" /></p>
</form>
</fieldset>
<?php
if( $cat ) {
	executeTool( $cat, $lang, $limit );
}
?>
</body>
</html>
