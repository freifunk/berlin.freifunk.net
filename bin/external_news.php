<?php

// Generate HTML snippets that list topics from various external sites.

// We want to advertise external resources and give an overview of recent topics.
// We don't want users to use these snippets as main entry point to external sites
// or provide deep links (that would be quite a link list, plus we do not want to
// link to "Re: Re: Re: a question" mailinglist postings).
// So this really just lists topics, not links.

function getHtml($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
  $result = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if($code!=200) {
    echo("failed to fetch $url (HTTP error $code)");
    exit(1);
  }
  return $result;
}

$debug = in_array("debug", $argv);

if(in_array("fflist", $argv)) {
  // FF Berlin mailinglist

  $listArchiveUrl = "https://lists.berlin.freifunk.net/pipermail/berlin/";
  // Find newest archive page
  $listHtml = getHtml($listArchiveUrl);
  preg_match("/Herunterladbare Version.*?<A href=\"([^\"]*?date.html)\".*?<A href=\"([^\"]*?date.html)\"/s", $listHtml, $matches);
  //echo $matches[1];
  //echo $matches[2];

  // Find postings (in two months to prevent near-empty list at beginning of month)
  $listHtml = getHtml($listArchiveUrl . $matches[2]);
  $listHtml .= getHtml($listArchiveUrl . $matches[1]);
  preg_match_all("/<A HREF=\"(\d\d\d\d\d\d.html)\">\[Berlin-wireless\] (.*?)<\/A>/s", $listHtml, $matches);
  //print_r($matches[2]);

  // Create topic list
  $topicList = [];
  $topicListLength = 0;
  foreach(array_reverse($matches[2]) as $match) {
    if(strpos($match, "Berlin Nachrichtensammlung")===0) continue;
    $match = trim($match);
    if(strlen($match)>70) $match = substr($match, 0, 70) . "...";
    $skipReason = "";
    foreach($topicList as $haveTopic) {
      if(levenshtein($haveTopic, $match)<(max(strlen($haveTopic), strlen($match))*0.7)) { $skipReason = $haveTopic; break; }
      if(strpos($haveTopic, substr($match, 0, 10))!==FALSE) { $skipReason = $haveTopic; break; }
    }
    if($skipReason!=="") {
      if($debug) print "$match ***SKIPPING B/C*** $skipReason\n";
      continue;
    }
    if($debug) print "ACCEPT $match\n";
    $topicListLength += strlen($match);
    if($topicListLength>350) break;
    array_push($topicList, $match);
  }

  // HTML escaping (not in debug mode)
  $result = [];
  if($debug) {
    echo "\n\n\n";
    $result = $topicList;
  } else {
    foreach($topicList as $topic) {
      $topic = str_replace(" ", "&nbsp;", $topic);
      $topic = str_replace("<", "&lt;", $topic);
      array_push($result, $topic);
    }
  }
  echo implode("&nbsp;• ", $result);
}

if(in_array("ffwiki", $argv)) {
  // wiki.freifunk.net

  $wikiRecentUrl = "https://wiki.freifunk.net/index.php?title=Spezial:Letzte_%C3%84nderungen&days=30&from=&limit=500";
  // Find recent changes of "Berlin:" pages
  $wikiHtml = getHtml($wikiRecentUrl);
  //<a href="/Berlin:Firmware" title="Berlin:Firmware" class="mw-changeslist-title">Berlin:Firmware</a>
  preg_match_all("/<a href=\"(\/Berlin:.*?)\".*?>(.*?)<\/a>/", $wikiHtml, $matches);
  //print_r($matches[2]);

  // Create wiki topic list
  $topicList = "";
  foreach($matches[2] as $match) {
    if(strlen($match)<5) continue;
    $match = trim($match);
    if(strpos($match, "\xe2\x86\x92") === 0) continue;
    $match = str_replace(" ", "&nbsp;", $match);
    $match = str_replace("<", "&lt;", $match);
    if(strpos($topicList, $match)===FALSE) {
      if(strlen($topicList)>350) break;
      if(strlen($topicList)>0) $topicList .= "&nbsp;• ";
      $topicList .= $match;
    }
  }
  if($topicList == "") {
    $topicList="<a href=\"https://wiki.freifunk.net/Berlin\">Berlin</a>";
  }
  echo $topicList;
}

?>
