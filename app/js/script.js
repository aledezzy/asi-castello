$(function () {
  $(document).scroll(function () {
    var $nav = $(".navGlobal");
    $nav.toggleClass("scrolled", $(this).scrollTop() > $nav.height());
  });
});

$(function () {
  $(document).scroll(function () {
    var $nav = $("#navVenue");
    $nav.toggleClass("scrolled", $(this).scrollTop() > $nav.height());
  });
});

$(function () {
  $(document).scroll(function () {
    var $nav = $("#navTickets");
    $nav.toggleClass("scrolled", $(this).scrollTop() > $nav.height());
  });
});

$(function () {
  $(document).scroll(function () {
    var $nav = $("#navAbout");
    $nav.toggleClass("scrolled", $(this).scrollTop() > $nav.height());
  });
});

//Sponsor Section on about page
//Create Rows
for (let i = 1; i <= 4; i++) {
  $("#sponsorSection").append($("<div/>", { class: "row sponsorRow" }));
}

//Create Columns
for (let i = 1; i <= 4; i++) {
  $(".sponsorRow").append($("<div/>", { class: "col-md-3 col-6 sponsorImages" }));
}

//Create IMGs
$(".sponsorImages").append($("<img/>", { class: "img-fluid sImages"}));

//Add img sources
const imgElement = document.querySelectorAll(".sImages");

for (let i = 1; i < 17; i++) {
  imgElement[i-1].src = `../images/sponsor-${i}.png`;
  imgElement[i-1].alt = `Sponsor Logo Image.`;
}
