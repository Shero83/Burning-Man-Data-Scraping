<?php
// Remove tags
function removeTags($str)
{
    $index = strpos($str, '>');
    $result = '';
    while ($index !== false)
    {
        $str = substr($str, $index+1);
        if (strlen($str) > 1)
            $result = $result . substr($str, 0, strpos($str, '<'));
        $index = strpos($str, '>');
    }
    return $result;
}

// Search for tags in a tweet
function searchTweet($title, &$tags)
{
    echo $title.'<br />';
    preg_match_all("/(#[a-z0-9][a-z0-9\-_]*)/i", $title, $matches);

    foreach ($matches as $rs) {
        foreach ($rs as $r) {
            $key = strtolower($r);
            if (array_key_exists($key, $tags))
                $tags[$key] = intval($tags[$key]) + 1;
            else
                $tags[$key] = 1;
        }

        if (count($rs) > 1) {
            return true;
        }
    }
    return false;
}

// Ftech + Union + Unique
function fetchFeed($source, &$tweets, &$tags)
{
    $doc = new DOMDocument();
    $doc->loadHTMLFile($source);
    $node = $doc->getElementById('stream-items-id');

    $items = (new SimpleXmlElement($doc->saveXML($node), LIBXML_NOCDATA));
    $items = $items->xpath("//div[@class='content']");

    foreach ($items as $item) {
        // Set the title
        $t = $item->xpath("./p[@class='TweetTextSize  js-tweet-text tweet-text']");
        $item->title = removeTags($t[0]->asXML());

        // Set the pubDate
        $t = $item->xpath("./div/small/a/span");
        $t = $t[0]->attributes();
        $s = (new DateTime('@' . $t['data-time']));
        $item->pubDate = $s->format('c');

        // Search for other tags in tweet
        $item->otherTag = searchTweet($item->title, $tags);
        array_push($tweets, $item);
    }
}

// Main program
function main()
{
    date_default_timezone_set('America/Los_Angeles');

    $url = "https://twitter.com/search?f=tweets&vertical=default&q=%23burningman&src=tyah";
    $tagsPath = "tags.txt";
    $tweetsPath = "tweets.txt";

    $tweets = array();
    $tags = array();

    // Read tags from file
    $myFile = fopen($tagsPath, "r") or die("Unable to open file!");
    while(!feof($myFile)) {
        $parts = preg_split("/,/", fgets($myFile));
        $tags[$parts[0]] = $parts[1];
    }
    fclose($myFile);

    // Fetch new feed
    fetchFeed($url, $tweets, $tags);

    // Append tweets to file
    $myFile = fopen($tweetsPath, "a+") or die("Unable to open file!");
    foreach($tweets as $tweet) {
        // Order of replacement
        $order   = array("\r\n", "\n", "\r");
        $replace = ' ';

        $tweet->title = str_replace($order, $replace, $tweet->title);
        $line = $tweet->title . "\t" . $tweet->pubDate . "\t" . $tweet->otherTag . "\n";
        fwrite($myFile, $line);
    }
    fclose($myFile);

    // Update tags to file
    $myFile = fopen($tagsPath, "w+") or die("Unable to open file!");
    foreach(array_keys($tags) as $key) {
        if ($key !== "") {
            $line = $key . "," . $tags[$key] . "\n";
            fwrite($myFile, $line);
        }
    }
    fclose($myFile);

    // TEST AREA
    //foreach(array_keys($tags) as $key)
    //    echo $key . ': ' . $tags[$key] . '<br />';
}

error_reporting(E_ERROR | E_PARSE);
main();
?>