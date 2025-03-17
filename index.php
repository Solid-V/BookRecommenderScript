<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookSuggestion</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
  <div class="container">
    <div class="centered">
    <form action="index.php" method="post"> 
        <label class="button-txt">Please enter a book</label> <br>
        <input class="input-btn" type="text" name="book"> <br>
        <input class ="submit-button" type="submit" name="button" value="Search"> <br>
    </form> 
    </div>
  </div>
</body>

</html>

<?php

session_start();
//API base url
$searchQuery = "https://openlibrary.org/search.json?q=";

// removes constant input after refreshing the page, need to understand this part
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $_SESSION['postdata'] = $_POST;
  unset($_POST);
  header("Location: ".$_SERVER['REQUEST_URI']);
  exit;
}

if (isset($_SESSION['postdata'])){
$_POST=$_SESSION['postdata'];
unset($_SESSION['postdata']);
}

global $book_input;
//takes input and reads it 
if (isset($_POST['button'])) {
    if (!empty($_POST['book'])) {
        $book_input = $_POST['book'];
      }  
    }

// API call section
if (!empty($book_input)) {
$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $searchQuery . urlencode($book_input));
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$result_json = curl_exec($curl);
curl_close($curl);

$result_data = json_decode($result_json);

//check for json error
if (json_last_error() !== JSON_ERROR_NONE){
  die("JSON error: " . json_last_error_msg());
}

if (isset($result_data->docs)) {

  echo "<div class='result'>";

  foreach($result_data->docs as $books) {
    
    echo "<form action='index.php' method='get'>";
    echo "<button  type='submit' class='buttonRecommend'  name='book_data' value='" . htmlspecialchars($books->key) . "'>"; 

    //Display the title of the book
    if (isset($books->title)){
      $title = $books->title;
      echo "Title: " . htmlspecialchars($title)  . "<br>";
    } else {
      echo "Title was not found" . "<br>";
    }

    // Display the name of the author(s)
    if (isset($books->author_name)){
      $authors = implode(", ", $books->author_name);
      echo "Author(s): " . $authors . "<br>";
    } else {
      echo "Author was not found" . "<br>";
    }

    // Display the OLID of the book
     if (isset($books->key)) {
      $olid = str_replace("/works/", "", $books->key);
      echo "OLID: " . $olid . "<br>";
    } else {
      echo "OLID not found" . "<br>";
    } 

    echo "</button>";
    echo "</form>";

  }
  echo "</div>";
} else {
  echo "Book not found";
}

}   

 if (isset($_GET['book_data'])) {
   $_SESSION['selected_book'] = $_GET['book_data'];
   header("Location: index.php"); //Refresh the page
   exit;
 }  

  if (!empty($_SESSION['selected_book'])) {
    $olid = str_replace("/works/", "", $_SESSION['selected_book']);
    $curl2 = curl_init();
    curl_setopt($curl2, CURLOPT_URL,"https://openlibrary.org/works/" . $olid . ".json?details=true");
    curl_setopt($curl2, CURLOPT_HEADER, false);
    curl_setopt($curl2, CURLOPT_RETURNTRANSFER, true);

    $subject_json = curl_exec($curl2);
    curl_close($curl2);
    
    $subject_data = json_decode($subject_json);

     if (isset($subject_data->subjects) && is_array($subject_data->subjects)) {

      $clean_subjects = [];
      foreach($subject_data->subjects as $subject) {
        $subject = trim($subject); // Remove extra spaces
        $split_subjects = array_map('trim', explode(",", $subject)); // Split subjects correctly
        foreach ($split_subjects as $s) {
        $s = strtolower($s);
        if (!preg_match('/nyt:|etc\./i', $s)) { // Remove unwanted metadata
            $clean_subjects[] = $s;
        }
    }

      } 
      $clean_subjects = array_unique($clean_subjects);
      // Re-index array
      $clean_subjects = array_values($clean_subjects);

      $random_subjects = array_rand($clean_subjects, 3); 
      $rc_subs = [];  //randomn and clear

      $rc_subs[0] =  $clean_subjects[$random_subjects[0]];
      $rc_subs[1] =  $clean_subjects[$random_subjects[1]];
      $rc_subs[2] =  $clean_subjects[$random_subjects[2]];

      $BaseURL = "https://openlibrary.org/subjects/";
      $urls = [$BaseURL . urlencode(strtolower($rc_subs[0])) . '.json', 
        $BaseURL . urlencode(strtolower($rc_subs[1])) . '.json',
        $BaseURL . urlencode(strtolower($rc_subs[2])) . '.json'];

      $mh = curl_multi_init();
      $curlHandles = [];
      $result = array();

      foreach ($urls as $i => $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, str_replace("+", "_", $url));
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_multi_add_handle($mh, $ch);
        $curlHandles[$url] = $ch;
      }

      $index = null;
      do {
        curl_multi_exec($mh, $index);
      } while ($index > 0);

      //get content and remove handles
      foreach($curlHandles as $url => $ch) {
        $result_book = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);

        $books = json_decode($result_book, true); 

        if(isset($books['works']) && is_array($books['works'])) {
          foreach ($books['works'] as $book) {
            if (isset($book['title'])) {
              echo "Title: " . htmlspecialchars($book['title']) . "<br>";
            }
          }
        }
      }
      //close 
      curl_multi_close($mh);

    }

    session_destroy(); // nuke session data 
  }
?>
