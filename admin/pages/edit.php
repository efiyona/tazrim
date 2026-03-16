<?php
  include("../../path.php");
  require ROOT_PATH . '/admin/assets/includes/main_data.php';
  require ROOT_PATH . '/admin/app/controllers/manager.php';
  check_login_status();

 $target = selectOne('setup', ['description' => "target"]);
?>

<html lang="he">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>מטרה- ניהול</title>
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

    <section class="container container--full flex--start--c gap30">
        <div class="breadcrumbs flex--start">
            עריכה / <span>&nbsp;עריכת פרטים</span>
        </div>
        <div class="row--start">
            <div class="col--70">
                <div class="card flex--stretch gap50">
                    <div class="card--header flex--space--between">
                        <div class="card--header--title">פרטי קמפיין</div>
                    </div>
                    <div class="card--body flex--start--c">
                        <form method="POST" enctype="multipart/form-data" class="form--container flex--start--c gap20 form--check">
                            <label class="form--label flex--start--c gap5">
                              יעד הקמפיין בשקלים (להוסיף פיסוק):
                              <input class="required" type="text" name="data" autocomplete="off" value="<?php echo $target['data']; ?>">
                            </label>
                            <br>
                            <label class="form--label flex--start--c gap5">
                                <input type="submit" class="btn btn--large flex--center gap10" name="update_campaign" value="עדכון פרטי קמפיין" autocomplete="off">
                            </label>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row--start">
          <div class="col--70">
            <div class="card flex--stretch gap50">
              <div class="card--header flex--space--between">
                  <div class="card--header--title">פרטי משתמש</div>
              </div>
              <div class="card--body flex--start--c">
                <form method="POST" enctype="multipart/form-data" class="form--container flex--start--c gap20 form--check">   
                  <div class="flex--start full--row gap30">
                    <label class="form--label flex--start--c gap5">
                      שם פרטי:
                      <input class="required" type="text" name="first_name" autocomplete="off" value="<?php echo $_SESSION['user']['first_name']; ?>">
                    </label>
                    <label class="form--label flex--start--c gap5">
                      שם משפחה:
                      <input class="required" type="text" name="last_name" autocomplete="off" value="<?php echo $_SESSION['user']['last_name']; ?>">
                    </label>
                  </div>
                  <label class="form--label flex--start--c gap5">
                    טלפון:
                    <input class="required" type="text" name="phone" autocomplete="off" value="<?php echo $_SESSION['user']['phone']; ?>">
                  </label>
                  <label class="form--label flex--start--c gap5">
                    סיסמה:
                    <input type="text" name="password" autocomplete="off">
                  </label>
                  <br>
                  <label class="form--label flex--start--c gap5">
                    <input type="submit" class="btn btn--large flex--center gap10" name="update_manager" value="עדכון פרטי משתמש" autocomplete="off">
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
  const forms = document.querySelectorAll('.form--check');
  
  forms.forEach(form => {
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
  });
}

  dropdownsClick();
  dropdownsHover();
  formCheck();

</script>

<script>
    const imageInputs = document.querySelectorAll('input[type="file"]');
    const imagePreviews = document.querySelectorAll('.preview--profile');

    imageInputs.forEach((imageInput, index) => {
    imageInput.addEventListener('change', (event) => {
        const file = event.target.files[0];
        const imagePreview = imagePreviews[index];

        if (file) {
        const reader = new FileReader();

        reader.onload = (e) => {
            imagePreview.src = e.target.result;
        };

        reader.readAsDataURL(file);
        } else {
        imagePreview.src = imagePreview.dataset.originalSrc;
        }
    });

    imagePreviews[index].dataset.originalSrc = imagePreviews[index].src;
    });
</script>
