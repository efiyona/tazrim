<?php
  include("../path.php");
  require ROOT_PATH . '/admin/assets/includes/main_data.php';
  require ROOT_PATH . '/admin/app/controllers/login.php';
?>

<html lang="he">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>אשר קורלק- ניהול</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" integrity="sha300000000000000000000000000000000000000000000084-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <script src="https://kit.fontawesome.com/9a47092d09.js" crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <?php require ROOT_PATH . '/admin/assets/includes/setup_meta_data.php'; ?>
</head>
<body dir="rtl">

    <?php require ROOT_PATH . '/admin/assets/sections/notification.php'; ?>

    <section class="navbar">
        <div class="navbar--container flex--center">
            <div class="navbar--logo flex--center gap10">
                <div class="logo--icon img--box">
                  <?php $school_logo = selectOne('photos', ['id' => 1]); ?>
                  <img src="<?php echo BASE_URL . "assets/images/" . $school_logo['image_folder'] . "/" . $school_logo['image_name']; ?>">
                </div>
            </div>
        </div>
    </section>

    <section class="container container--full container--no-navber flex--center--c">
      <div class="breadcrumbs flex--center">
      אשר קורלק // <span>&nbsp;ניהול</span>
      </div>
      <div class="row">
        <div class="col--50">
          <div class="card flex--stretch gap50">
            <div class="card--body flex--start--c">
              <form method="POST" enctype="multipart/form-data" class="form--container flex--start--c gap15" id="form_check">
                <label class="form--label flex--start--c gap15">
                  טלפון:
                  <input class="required" type="text" name="phone" autocomplete="off">
                </label>
                <label class="form--label flex--start--c gap5">
                  סיסמה:
                  <input class="required" type="text" name="password" autocomplete="off">
                </label>
                <br>
                <label class="form--label flex--start--c gap5">
                  <input type="submit" class="btn btn--large flex--center gap10" name="login" value="התחברות" autocomplete="off">
                </label>
              </form>
            </div>
          </div>
        </div>
      </div>
    </section>
</body>

<script>
  
function dropdownsClick() {
  const dropdownElements = document.querySelectorAll('.dropdown');
  const body = document.querySelector('body'); // Selects the body element

  // Event listener for click on body
  body.addEventListener('click', (event) => {
    // Check if the clicked element is not within a dropdown
    const isOutsideDropdown = !event.target.closest('.dropdown');

    // Close all open dropdown menus if clicked outside a dropdown
    if (isOutsideDropdown) {
      document.querySelectorAll('.dropdown--menu--show').forEach(menu => {
        menu.classList.remove('dropdown--menu--show');
      });
    }
  });

  dropdownElements.forEach(dropdown => {
    const dropdownMenu = dropdown.querySelector('.dropdown--menu');
    const dropdownToggle = dropdown.querySelector('a, .avatar');

    // Event listener for click on dropdown toggle
    dropdownToggle.addEventListener('click', (event) => {
      // Prevent body click event from closing the dropdown
      event.stopPropagation();

      // Check if the clicked dropdown's menu is already open
      const isOpen = dropdownMenu.classList.contains('dropdown--menu--show');

      // Toggle the clicked dropdown menu
      dropdownMenu.classList.toggle('dropdown--menu--show', isOpen ? false : true);
    });
  });
}

function dropdownsHover() {
  const dropdownElements = document.querySelectorAll('.dropdown--hover');

  dropdownElements.forEach(dropdown => {
    const dropdownMenu = dropdown.querySelector('.dropdown--menu--hover');
    const dropdownToggle = dropdown.querySelector('a, .avatar');
    dropdown.addEventListener('mouseenter', () => {
      dropdownMenu.classList.add('dropdown--menu--hover--show');
    });

    dropdown.addEventListener('mouseleave', () => {
      dropdownMenu.classList.remove('dropdown--menu--hover--show');
    });

    dropdownMenu.addEventListener('mouseleave', () => {
      dropdownMenu.classList.remove('dropdown--menu--hover--show');
    });
  });
}

function searchFilter() {
  const searchInput = document.getElementById("search");
  const table = document.getElementById("table");

  searchInput.addEventListener("keyup", function() {
      const searchTerm = searchInput.value.toLowerCase();
      const rows = table.getElementsByTagName("tr");

      for (let i = 1; i < rows.length; i++) {
          const cells = rows[i].getElementsByTagName("td");
          let found = false;

          for (let j = 0; j < cells.length; j++) {
              const text = cells[j].textContent.toLowerCase();

              if (text.includes(searchTerm)) {
                  found = true;
                  break;
              }
          }

          if (found) {
              rows[i].style.display = "";
          } else {
              rows[i].style.display = "none";
          }
      }
  });
}

function formCheck() {
  const form = document.querySelector('#form_check');
  if(form){
    const submitButton = form.querySelector('input[type="submit"]');

    // פונקציה לבדיקת מילוי שדה
    function isFieldFilled(field) {
      return field.value !== '';
    }

    function updateSubmitButton() {
      // מציאת כל השדות בעלי המחלקה "required"
      const requiredFields = form.querySelectorAll('input.required, select.required');
    
      // בדיקה אם כל השדות הנדרשים מלאים
      const allRequiredFieldsFilled = Array.from(requiredFields).every(isFieldFilled);
    
      // עדכון מצב כפתור השליחה והוספת/הסרת מחלקה "empty"
      submitButton.disabled = !allRequiredFieldsFilled;
    
      if (!allRequiredFieldsFilled) {
        submitButton.classList.add('empty--btn');
      } else {
        submitButton.classList.remove('empty--btn');
      }
    
      // הוספה/הסרה של מחלקת "empty" מהלבלים של השדות הנדרשים
      requiredFields.forEach(field => {
        const label = field.parentNode;
    
        if (isFieldFilled(field)) {
          label.classList.remove('empty');
        } else {
          label.classList.add('empty');
        }
      });
    }
    
    // הוספת אירוע "keyup" לכל שדה נדרש
    form.querySelectorAll('input.required, select.required').forEach(field => {
      field.addEventListener('keyup', updateSubmitButton);
    });
    
    // עדכון התחלתי של מצב כפתור השליחה
    updateSubmitButton();
  }
}

  dropdownsClick();
  dropdownsHover();
  formCheck();

</script>