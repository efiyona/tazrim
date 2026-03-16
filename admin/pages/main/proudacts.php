<?php
  include("../../../path.php");
  require ROOT_PATH . '/admin/assets/includes/main_data.php';
  require ROOT_PATH . '/admin/app/controllers/proudacts.php';
 check_login_status();

  $proudacts = selectAll('proudacts');

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

    <section class="container container--full flex--start--c">
        <div class="breadcrumbs flex--start">
            כפתורים / <span>&nbsp;רשימת כפתורים</span>
        </div>
        <div class="row">
            <div class="col--95">
                <div class="card flex--stretch gap50">
                    <div class="card--header flex--space--between flex--center-mobile">
                        <div class="card--search">
                            <label class="flex--center gap15">
                                חיפוש:
                                <input type="search" id="search" autocomplete="off">
                            </label>
                        </div>
                        <div class="card--header--btns flex--end gap10">
                            <div class="card--header--btn">
                                <a href="<?php echo BASE_URL;?>admin/pages/add/proudact.php" class="btn flex--center gap10">
                                    כפתור חדש
                                    <i class="fa-solid fa-plus"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php if(count($proudacts) > 0){ ?>
                        <div class="card--body flex--start--c">
                            <table class="card--table table" id="table">
                                <thead>
                                    <tr>
                                        <th>תיאור</th>
                                        <th>סכום</th>
                                        <th>פעולות</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($proudacts as $proudact){ ?>
                                        <tr>
                                            <td class="flex--start gap10 table_second"><?php echo $proudact['description']; ?></td>
                                            <td class="flex--start gap10 table_second"><?php if($proudact['price'] != null){ echo $proudact['price']; }else{ echo "חופשי"; } ?></td>
                                            <td class="flex--center gap10">
                                                <div class="table-options flex--start gap20">
                                                    <a href="<?php echo BASE_URL . "admin/pages/edit/proudacts.php?update_id=" . $proudact['id']; ?>" class="tooltip">
                                                      <i class="fa-solid fa-pen-to-square"></i>
                                                      <span class="tooltiptext">עריכה</span>
                                                    </a>
                                                </div>
                                                <div class="table-options flex--start gap20">
                                                    <a href="<?php echo BASE_URL . "admin/pages/main/proudacts.php?delete_id=" . $proudact['id']; ?>" class="tooltip confirmation" data-confirm-text="למחוק את הכפתור?">
                                                      <i class="fa-solid fa-trash red"></i>
                                                      <span class="tooltiptext">מחיקה</span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php }; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php }else{ ?>
                        <div class="card--body flex--center--c">לא קיימים כפתורים</div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </section>

    <?php require ROOT_PATH . '/admin/assets/sections/confirmation_popup.php'; ?>
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
  searchFilter();
  formCheck();

</script>

<script>
  // פופאפ פרטי תרומה
  document.querySelectorAll('.donation-btn').forEach(link => {
    link.addEventListener('click', function(event) {
        event.preventDefault();

        const fullName = this.getAttribute('data-full-name-text');
        const phone = this.getAttribute('data-phone-text');
        const priceText = this.getAttribute('data-price-text');
        const descriptionText = this.getAttribute('data-description-text');
        const popup = document.getElementById('donation-popup');

        document.querySelector('.modal-container-full-name').textContent = fullName;
        document.querySelector('.modal-container-price').textContent = phone;
        document.querySelector('.modal-container-phone').textContent = priceText;
        document.querySelector('.modal-container-description').textContent = descriptionText;

        popup.style.display = 'block';

        document.getElementById('donation-popup-exit-btn').onclick = function() {
            popup.style.display = 'none';
        };

        popup.addEventListener('click', function(event) {
            if (event.target === popup) {
                popup.style.display = 'none';
            }
        });
    });
});
</script>

