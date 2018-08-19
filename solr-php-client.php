<?php
// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');

include 'spell-corrector/SpellCorrector.php';

$limit = 10;
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;
$results = false;
$suggests = false;
$corrected = false;
$correctedSpelling = "";
$instead = isset($_REQUEST['instead']) ? $_REQUEST['instead'] : false;


$sortby = null;
if(isset($_REQUEST['sortby'])) {
  $sortby = $_REQUEST['sortby'];
}

if ($query)
{
  // The Apache Solr Client library should be on the include path
  // which is usually most easily accomplished by placing in the
  // same directory as this script ( . or current directory is a default
  // php include path entry in the php.ini)
  require_once('solr-php-client/Apache/Solr/Service.php');

  // create a new solr service instance - host, port, and webapp
  // path (all defaults in this example)
  $solr = new Apache_Solr_Service('localhost', 8983, '/solr/myexample/');

  // if magic quotes is enabled then stripslashes will be needed
  if (get_magic_quotes_gpc() == 1)
  {
    $query = stripslashes($query);
  }


  $queryStrSplitArr = explode(" ", $query);
  $strSplitArrLen = sizeof($queryStrSplitArr);
  $strTemp = ""; // new corrected query string
  if($strSplitArrLen > 1) {
    /* correct each word one by one and concat together */
    for($i = 0; $i < $strSplitArrLen; $i++) {
      $lowerStrQuery = strtolower($queryStrSplitArr[$i]);
      $correctedWord = SpellCorrector::correct($lowerStrQuery);

      if($i != ($strSplitArrLen - 1)) { 
        /* not last element in str array */
        $strTemp .= $correctedWord . " ";
      } else {
        $strTemp .= $correctedWord;
      }
    }
    $correctedSpelling = $strTemp;
  } else {
    /* single query word */
    $lowerStrQuery = strtolower($query);
    $correctedSpelling = SpellCorrector::correct($lowerStrQuery);
  }

  if(strcmp($correctedSpelling, strtolower($query)) !=0 ) {
    /* error in query spelling by SpellCorrector */
    $corrected = true;
  } else {
    $corrected = false;
  }

  // in production code you'll always want to use a try /catch for any
  // possible exceptions emitted  by searching (i.e. connection
  // problems or a query parsing error)
  try
  {
    if(strcmp($sortby, "lucene") == 0) {
      $additionalParameters = array(
      'fl' => array(
        'id', 
        'title', 
        'og_url', 
        'description',
        'og_description'
      ),
      'wt' => 'json');
    } else {
      $additionalParameters = array(
      'sort' => 'pageRankFile desc',  
      'fl' => array(
        'id', 
        'title', 
        'og_url', 
        'description',
        'og_description'
      ),
      'wt' => 'json');
    }

    if($instead == true) {
      $corrected = false; // if search instead, corrected is ignored hence forced to true
    }

    if($corrected) {
      $results = $solr->search($correctedSpelling, 0, $limit, $additionalParameters);
    } else {
      $results = $solr->search($query, 0, $limit, $additionalParameters);
    }
    
    

  }
  catch (Exception $e)
  {
    // in production you'd probably log or email this error to an admin
    // and then show a special message to the user but for this example
    // we're going to show the full exception
    die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
  }
}

?>


<?php
// echo $instead;
// echo 'Spelling Corrector Testing! <br/>';
$str1 = 'elon musssk';

$str = 'Donald Trup';
$strArr = explode(" ", $str);
$arrLen = sizeof($strArr);
$temp = "";

for($i = 0; $i < $arrLen; $i++) {
  $corrStr = SpellCorrector::correct($strArr[$i]);
  // echo $corrStr . "<br />";
  if($i != ($arrLen - 1)) { 
    /* not last element in str array */
    $temp .= $corrStr . " ";
  } else {
    $temp .= $corrStr;
  }
}

//echo $temp . "<br />"; // correcting word by word
//echo SpellCorrector::correct($str); // corrected entire word without invidual
//echo "<br\> <br\>";

?>


<style type="text/css">
a:link {
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}
</style>







<html>
  <head>
    <title>HW4 - Solr - Fox News</title>
  <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/smoothness/jquery-ui.css">
  <script src="//code.jquery.com/jquery-1.12.4.js"></script>
  <script src="//code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

  </head>
  <body>
    

    <div style="text-align: center">

<h2> <a href="http://localhost/hw5/solr-php-client.php"> HW5 </a> </h2>
  <br/>

    <form  accept-charset="utf-8" method="get">
      <label for="q">Search:</label>
      <input id="q" name="q" type="text" list="s" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>" onkeyup="mySuggestion(event)" onblur="closeSuggestion()"/>

      <div id="s" style="display: none;">
      </div>

      <br/><br/>
      Sort:
      <input type="radio" name="sortby" value="lucene"  
        <?php 
        if ($query && (strcmp($sortby, "lucene") == 0))
        {
          echo "checked";
        } else if (!$query && ($sortby == NULL)) 
        {
          echo "checked";
        }?>> Lucene(Default)
      <input type="radio" name="sortby" value="pagerank"
        <?php 
        if ($query && (strcmp($sortby, "pagerank") == 0))
        {
          echo "checked";
        } ?>> PageRank
      <br/><br/>
      <input type="submit"/>
    </form>
  </div>

<?php

/* Get first sentence containing the query terms */
function getParagraphSnippet($url, $term) {
  $file = file_get_contents($url);
  $dom = new DOMDocument();
  @$dom->loadHTML($file);

  /* split query into many terms */
  $termArr = explode(" ", $term);
  $termArrLen = sizeof($termArr);

  $candidates = [];
  $z = 0;
  foreach($dom->getElementsByTagName('p') as $p) {
    $score = 0;
    $pStr = $p->nodeValue;
    /* found a match for entire term - case insensitive */
    if(preg_match("/\b" . $term . "\b/i", strtolower($pStr))) {
    // if(stripos($pStr, $term) != false) {
      $score = $termArrLen + 1;
      /* if score already exists, don't add bc the preceding setence containing query term exsists */
      if(array_key_exists($score, $candidates) == false) {
        $candidates[$score] = $pStr;
      }
      
    } else {
      /* check if greater than many terms */
      if($termArrLen > 1) {
        $score = 0; /* score based on num terms matched; will be at best termArrLen */
        for($j = 0; $j < $termArrLen; $j++) {
          if(preg_match("/\b" . $termArr[$j] . "\b/i", strtolower($pStr))) {
          // if(stripos($pStr, $termArr[$j]) != false) {
            $score += 1;
          }
        }

        if($score > 0) {
          /* if score already exists, don't add bc the preceding setence containing query term exsists */
          if(array_key_exists($score, $candidates) == false) {
            $candidates[$score] = $pStr;
          }
        }
      }

    }
    $z++;
  }

  /* highest possible score = $termArrLen + 1 */
  for($s = ($termArrLen + 1); $s > 0; $s--) {
    if(array_key_exists($s, $candidates)) {

      $pStrLen = strlen($candidates[$s]);
      if($pStrLen > 160) {
        /* need to trim string */

        $stringTrimed = substr($candidates[$s],0,160);
        $stringTrimed = trimString($candidates[$s], strtolower($term), 160);
        // return $candidates[$s];
        return $stringTrimed;


      }
      return $candidates[$s];
    }
  }
  return NULL;
}

function posInArray($arr, $key) {
  for($i = 0; $i < sizeof($arr); $i++) {
    if(preg_match("/\b" . $key . "\b/i", strtolower($arr[$i]))) {
      // echo $key;
      return $i;
    }
  }
  return -1;
}

function trimString($str, $key, $maxLen) {
  $strLen = strlen($str);

  $keyArr = explode(' ', $key);
  $strArr = explode('/(?<=[.?!])\s+(?=[a-z])/i', $str); // split by sentence

  $sentPosArr = [];
  for($i = 0; $i < sizeof($keyArr); $i++) {
    $pos = posInArray($strArr, strtolower($keyArr[$i]));
    if($pos < 0) {
      // term not found in sentence
    } else {
      // echo $pos . "<br />";
      array_push($sentPosArr, $pos);
    }
  }

  if(sizeof($sentPosArr) > 0) {
    return $strArr[$sentPosArr[0]];
  } else {
    return NULL;
  }

}

function keepDescriptionTag($descriptionStr, $key) {
  $keyArr = explode(' ', $key);

  if(preg_match("/\b" . strtolower($key) . "\b/i", strtolower($descriptionStr))) {
    return true;
  } else {
    for($i = 0; $i < sizeof($keyArr); $i++) {
      if(preg_match("/\b" . strtolower($keyArr[$i]) . "\b/i", strtolower($descriptionStr))) {
        return true;
      }
    }
  }
  return false;
}


function boldString($pSnippet, $key) {

  $keyArr = explode(' ', $key);
  $temp = $pSnippet;
  for($i = 0; $i < sizeof($keyArr); $i++) {
    $keyTerm = $keyArr[$i];
    $temp = str_ireplace($keyTerm, "<b>".$keyTerm."</b>", $temp);
  }

  return $temp;
}



// display results
if ($results)
{
  $qterm = $results->responseHeader->params->q;
  $total = (int) $results->response->numFound;
  $start = min(1, $total);
  $end = min($limit, $total);



?>

  <div id="spellCorrectorDiv"> <?php 
    if($corrected) {
      echo "Showing Results for " . "<b> <i>" . $correctedSpelling . "</i> </b> <br />";

      if($instead == false) {
        $insteadURL = "//{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}" . "&instead=1";
      }
      
      echo "Search instead for <a href=\"". $insteadURL . "\">" . $query . "</a>";

    } ?></div> <br/>


    <div>Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:</div>
    <ol>


<?php
  // iterate result documents
  foreach ($results->response->docs as $doc)
  {
?>
      <li>
        <table style="border: 0px solid black; text-align: left">

          <tr>
            <!-- Title -->
            <td style="font-size: 130%;"><?php 
            $url_str = htmlspecialchars($doc->og_url, ENT_NOQUOTES, 'utf-8');
            $title_str = htmlspecialchars($doc->title, ENT_NOQUOTES, 'utf-8');

            echo "<a href=\"" . $url_str . "\">" . $title_str . "</a>"; ?></td>
          </tr>

          <tr>
            <!-- URL -->
            <td style="font-size: 90%;"><?php 
            $url_str = htmlspecialchars($doc->og_url, ENT_NOQUOTES, 'utf-8');
            echo "<a href=\"" . $url_str . "\" style=\"color: green\">" . $url_str . "</a>"; ?></td>
          </tr>

          <tr>
            <!-- ID -->
            <td style="font-size: 90%;"><?php echo htmlspecialchars($doc->id, ENT_NOQUOTES, 'utf-8'); ?></td>
          </tr>

          <tr>
            <!-- Description -->
            <td style="font-size: 90%;"><?php 
            if (($doc->description == null) && ($doc->og_description == null)) {
              $pSnippet = getParagraphSnippet($url_str, $qterm);
              $fSnippet = boldString($pSnippet, $qterm);
              if($pSnippet != NULL) {
                echo $fSnippet;
              } else {
                echo "N/A";
              }
              
            } else {
              if(($doc->og_description != null)) {
                if(keepDescriptionTag($doc->og_description, $qterm)) {
                  // echo $nowhitespace . "<br />";
                  // echo $doc->og_description . "<br />";
                  $nowhitespace = preg_replace('/[\x00-\x1F\x80-\xFF\s\t\n\r\s]+/', ' ', $doc->og_description);
                  $stringTrimed = trimString($nowhitespace, strtolower($qterm), 160);
                  $fSnippet = boldString($stringTrimed, $qterm);
                  echo $fSnippet; // echo $stringTrimed;
                } else {
                  $pSnippet = getParagraphSnippet($url_str, $qterm);
                  if($pSnippet != NULL) {
                    $fSnippet = boldString($pSnippet, $qterm);
                    echo $fSnippet;
                  } else {
                    echo "N/A";
                  }
                }
              } else {
                if(is_array($doc->description)) {
                  for($i = 0; $i < sizeof($doc->description); $i++) {
                    if(keepDescriptionTag($doc->description[$i], $qterm)) {

                      $nowhitespace = preg_replace('/[\x00-\x1F\x80-\xFF\s\t\n\r\s]+/', ' ', $doc->description[$i]);
                      $stringTrimed = trimString($nowhitespace, strtolower($qterm), 160);
                      $fSnippet = boldString($stringTrimed, $qterm);
                      echo $fSnippet; // echo $stringTrimed;
                      // echo htmlspecialchars($doc->description[$i], ENT_NOQUOTES, 'utf-8');
                    } else {
                      $pSnippet = getParagraphSnippet($url_str, $qterm);
                      if($pSnippet != NULL) {
                        $fSnippet = boldString($pSnippet, $qterm);
                        echo $fSnippet;
                      } else {
                        echo "N/A";
                      }
                    }
                  }
                } else {

                  if(keepDescriptionTag($doc->description, $qterm)) {
                    $nowhitespace = preg_replace('/[\x00-\x1F\x80-\xFF\s\t\n\r\s]+/', ' ', $doc->description);
                    $stringTrimed = trimString($nowhitespace, strtolower($qterm), 160);
                    $fSnippet = boldString($stringTrimed, $qterm);
                    echo $fSnippet; // echo $stringTrimed;
                    // echo htmlspecialchars($doc->description, ENT_NOQUOTES, 'utf-8');
                  } else {
                    $pSnippet = getParagraphSnippet($url_str, $qterm);
                    if($pSnippet != NULL) {
                      $fSnippet = boldString($pSnippet, $qterm);
                      echo $fSnippet;
                    } else {
                      echo "N/A";
                    }
                  }

                }
              }
            } ?></td>
          </tr>
        </table>
      </li>
      <br />
<?php
  }
?>

    </ol>
<?php
}
?>



<div id="testing">
    </div>

    <div id="testing2"> 
    </div>

    <br/>
    <br/>

    <div id="demo"> 
    </div>



<script>
$( "#q" ).autocomplete({
  source: function( request, response ) {

    console.log("hello");

    var dataList = document.getElementById('s');
    console.log("in autocomplete: " + dataList.children.length);
    var terms = [];

    var i;
    for(i = 0; i < dataList.children.length; i++) {
      console.log(dataList.children[i].value);
      terms.push(dataList.children[i].value);
    }
          response( $.grep( terms, function( item ){
              return true;
          }) );
      }
});

function loadSuggestions(q) {

  var queryTerm = (q.trim()).split(" ");
  if(queryTerm.length == 1) {

    console.log("in if statement 1");

  $.ajax({
  'url': 'http://localhost:8983/solr/myexample/suggest',
  'data': {'wt':'json', 'q':q},
  'success': function(data) { 

    var suggestObj = data.suggest.suggest;
    console.log(suggestObj);
    var suggestKey = Object.keys(suggestObj)[0];
    console.log("Suggest query: " + suggestKey);
    var numFound = suggestObj[suggestKey].numFound;
    
    var dataList = document.getElementById('s');
    console.log("Before " + dataList.children.length);
    while (dataList.hasChildNodes()) {  
      dataList.removeChild(dataList.firstChild);
    } 
    console.log("After " + dataList.children.length);

    var tags = [];

    if(numFound > 0) {
      var suggestArr = suggestObj[suggestKey].suggestions;
      console.log("Listing!");


      for(x in suggestArr) {
        // console.log(suggestArr[x].term + "," + suggestArr[x].weight);

        // problem with chrome and firefox where it does its own prefix matching
        // so if the suggestions do not match the prefix of the query
        // no suggestions will show

        var option = document.createElement('option');
        option.value = suggestArr[x].term;
        dataList.append(option);
        tags.push(option.value);
        console.log(option.value);
      } 


    }
    
    console.log(dataList.children.length);
    $('.option').show();
  },
  'dataType': 'jsonp',
  'jsonp': 'json.wrf'
  });

  } else if (queryTerm.length > 1) {

    console.log("in if statement 2");

    var dataList = document.getElementById('s');
    while (dataList.hasChildNodes()) {  
      dataList.removeChild(dataList.firstChild);
    } 


    var temp = [];

$.each(queryTerm, function(index, value) {

  console.log("in each " + index + " " + value);

  $.ajax({
  'url': 'http://localhost:8983/solr/myexample/suggest',
  'data': {'wt':'json', 'q':value},
  'success': function(data) {


    var suggestObj = data.suggest.suggest;
    var suggestKey = Object.keys(suggestObj)[0];
    var numFound = suggestObj[suggestKey].numFound;

    console.log(suggestObj);
    console.log("Suggest query: " + suggestKey);
    
    if(numFound > 0) {
      var suggestArr = suggestObj[suggestKey].suggestions;
      var terms = [];

      var c = 0;
      for(x in suggestArr) {

        console.log("pusshing ... " + index + " .. " +  suggestArr[x].term); 
        terms.push(suggestArr[x].term);

        if(dataList.children.length < 5) {
          // var option = document.createElement('option');
          // option.value = suggestArr[x].term;
          // dataList.append(option);
        } else {
          var cn = dataList.children[c];
          // console.log(cn.value + " " + suggestArr[x].term) ;
          // dataList.children[c].value = cn.value + " " + suggestArr[x].term;
        }

        c++;
        temp[index] = terms;
        console.log("size of temp = " + temp.length);
        console.log(temp);
      }

        if(temp.length == queryTerm.length) {

          var finalTemp = [];
          for(idx = 0; idx < queryTerm.length; idx++) {
            if(temp[idx].length == 5) {

            var termList = temp[idx];

            for(jdx = 0; jdx < termList.length; jdx++) {
              if(finalTemp.length < 5) {
                finalTemp.push(termList[jdx]);
              } else {
                finalTemp[jdx] += " " + termList[jdx];
              }
            }

            }

          }
          // combined all term list from each query term in finalTemp

          for(zdx = 0; zdx < finalTemp.length; zdx++) {
            var option = document.createElement('option');
            option.value = finalTemp[zdx];
            dataList.append(option);
          }
          // append to datalist

      } 
    }
    
    $('.option').show();

    flag = index;
  },
  'dataType': 'jsonp',
  'jsonp': 'json.wrf'
  });

  console.log("done with index = " + index);

});




  }





};



function mySuggestion(event) {

  /* Return if arrow up, arrow down or enter are pressed */
  if( event.keyCode==40 || event.keyCode==38 || event.keyCode==13 ) {
    return;
  }

  var input = document.getElementById("q");
  var lenInput = input.value.length;
  // document.getElementById("testing").innerHTML = input.value;

  var xmlhttp = new XMLHttpRequest();
  var url = "http://localhost:8983/solr/myexample/suggest?q=" + input.value;
  // document.getElementById("testing2").innerHTML = url;

  var regx = /^[A-Za-z0-9/\s/]+$/;
  // var tx = "falcon h";
  // console.log(regx.test(tx));
  if (regx.test(input.value)) {

        loadSuggestions(input.value.toLowerCase());
  }

  
};


function closeSuggestion() {
  document.getElementById("s").style.visibility = "hidden";
}

</script>


  </body>
</html>