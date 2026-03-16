<?php
  include("../../path.php");
  require ROOT_PATH . '/admin/assets/includes/main_data.php';
  $permission_page = ['managers'];
  check_login_status();
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
    
  <?php require ROOT_PATH . '/admin/assets/sections/navbar.php'; ?>
  <?php require ROOT_PATH . '/admin/assets/sections/menu_horizontal.php'; ?>
  <?php require ROOT_PATH . '/admin/assets/sections/menu_vertical.php'; ?>

  <section class="container container--full">
      <div class="row">
          <div class="col--50 col--90--mobile">
              <div class="card flex--stretch gap50">
                  <div class="card--header flex--space--between">
                      <div class="card--header--title">סטטיסטיקות</div>
                      <div class="card--header--sub--title">עודכן לאחרונה</div>
                  </div>
                  <div class="card--body flex--space--between flex--start-mobile">
                      <div class="card--mini--box flex--center gap15">
                          <div class="mini--box--icon icon--main">
                              <i class="fa-regular fa-face-smile-beam"></i>
                          </div>
                          <div class="mini--box--detials flex--start--c">
                              <?php $transactions_count = selectAll('transactions') ?>
                              <?php echo count($transactions_count); ?>
                              <span>תרומות</span>
                          </div>
                      </div>
                      <div class="card--mini--box flex--center gap15">
                          <div class="mini--box--icon icon--blue">
                              <i class="fa-regular fa-rectangle-list"></i>
                          </div>
                          <div class="mini--box--detials flex--start--c">
                              <?php
                                  $proudacts_count = selectAll('proudacts');
                              ?>
                              <?php echo count($proudacts_count); ?>
                              <span>כפתורים</span>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
          <div class="col--40 col--90--mobile">
              <div class="card flex--stretch gap50">
                  <div class="card--header flex--space--between">
                      <div class="card--header--title">קישורים מהירים</div>
                  </div>
                  <div class="card--body flex--start gap20">
                    <div class="card--body--btn">
                      <a href="<?php echo BASE_URL; ?>" class="btn flex--center" target="_blank">כניסה לאתר</a>
                    </div>
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
