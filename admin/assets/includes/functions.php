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