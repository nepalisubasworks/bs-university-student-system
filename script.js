function showForm(formId) {
    document.querySelectorAll(".form-box").forEach(form => form.classList.remove("active"));
    document.getElementById(formId).classList.add("active");
}

function showCourses(selectedCourse) {
    var faculty = document.getElementById('faculty').value;
    var courseBox = document.getElementById('course');

    // coursesData is injected by index.php from the database
    var courses = (typeof coursesData !== 'undefined') ? coursesData : {};

    courseBox.innerHTML = '<option value="" disabled selected>Select Course</option>';

    if (courses[faculty]) {
        courses[faculty].forEach(function(course) {
            var option = document.createElement('option');
            option.value = course;
            option.text = course;
            if (selectedCourse && course === selectedCourse) {
                option.selected = true;
            }
            courseBox.appendChild(option);
        });
    }
}

window.addEventListener('DOMContentLoaded', function() {
    var facultySelect = document.getElementById('faculty');
    var courseInitial = document.getElementById('course');
    if (facultySelect && facultySelect.value !== '' && courseInitial) {
        var oldCourse = courseInitial.getAttribute('data-old-course');
        showCourses(oldCourse);
    }
});

// Scrollbar visible while actively touching/moving inside the ledger,
// hides after 2 seconds of no movement, hides instantly when cursor leaves
var tableWrap = document.querySelector('.table-wrap');
var scrollHideTimer;

function showScrollbar() {
    if (tableWrap) {
        tableWrap.classList.add('scroll-active');
        clearTimeout(scrollHideTimer);
        scrollHideTimer = setTimeout(function() {
            tableWrap.classList.remove('scroll-active');
        }, 2000);
    }
}

if (tableWrap) {
    tableWrap.addEventListener('mousemove', showScrollbar);
    tableWrap.addEventListener('scroll', showScrollbar);

    tableWrap.addEventListener('mouseleave', function() {
        clearTimeout(scrollHideTimer);
        tableWrap.classList.remove('scroll-active');
    });
}
function toggleMenu(btn) {
    var dropdown = btn.nextElementSibling;
    document.querySelectorAll('.action-dropdown.open').forEach(function(d) {
        if (d !== dropdown) d.classList.remove('open');
    });
    dropdown.classList.toggle('open');
}

document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('btn-menu')) {
        document.querySelectorAll('.action-dropdown.open').forEach(function(d) {
            d.classList.remove('open');
        });
    }
});