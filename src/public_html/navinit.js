var isLoggedIn = null;
var isAdmin = null;
var userId = null;

function checkLoggedIn(callback) {
  $.ajax({
    url: "/api/v1/login",
    type: "GET",
    dataType: "json",
  }).then(
    function (loginResponse) {
      callback(loginResponse);
    },
    function () {
      var loginResponse = {
        code: 1,
        message: "Error, Not Logged In",
        isLoggedIn: false,
        isAdmin: false,
      };
      callback(loginResponse);
    }
  );
}

function getIsLoggedIn(callback) {
  if (isLoggedIn === null) {
    checkLoggedIn(function (loginResponse) {
      isLoggedIn = loginResponse.isLoggedIn;
      callback(isLoggedIn);
    });
  } else {
    callback(isLoggedIn);
  }
}

function getIsAdmin(callback) {
  if (isAdmin === null) {
    checkLoggedIn(function (loginResponse) {
      isAdmin = loginResponse.isAdmin;
      isLoggedIn = loginResponse.isLoggedIn;
      userId = loginResponse.userId;
      callback(isAdmin);
    });
  } else {
    callback(isAdmin);
  }
}

function getUserId(callback) {
  if (userId === null) {
    checkLoggedIn(function (loginResponse) {
      userId = loginResponse.userId;
      isLoggedIn = loginResponse.isLoggedIn;
      isAdmin = loginResponse.isAdmin;
      callback(userId);
    });
  } else {
    callback(userId);
  }
}

// Called from each page's .load("navigation.html", ...) callback
function initNavigation() {
  // Signup modal (only if placeholder exists on the page)
  if ($("#signup-modal-placeholder").length) {
    $("#signup-modal-placeholder").load("signupmodal.html", function () {
      $("#signup").on("shown.bs.modal", function () {
        $("#signUpEmailInput").trigger("focus");
      });

      var signUpForm = $("#signupForm");
      $("#buttonSignUp").click(function () {
        if (signUpForm[0].checkValidity()) {
          var email = $("#signUpEmailInput").val();
          var pw = $("#confirmPassword").val();
          var postUserData = JSON.stringify({
            email: email,
            password: pw,
          });
          $.ajax({
            url: "/api/v1/User",
            type: "POST",
            data: postUserData,
            dataType: "json",
          }).then(function (cmdResponse) {
            if (cmdResponse.code == "0") {
              $("#signUpEmailExistsAlert").addClass("collapse");
              var signUpModalElement = document.querySelector("#signup");
              var signUpModal =
                bootstrap.Modal.getOrCreateInstance(signUpModalElement);
              signUpModal.hide();
              $("#accountCreatedAlert").removeClass("collapse");
              $("#loginUsername").val($("#signUpEmailInput").val());
            } else if (cmdResponse.code == "1") {
              $("#signUpEmailExistsAlert").removeClass("collapse");
            } else {
              $("#signUpOtherExistsAlert").text(cmdResponse.message);
              $("#signUpOtherExistsAlert").removeClass("collapse");
            }
          });
        } else {
          signUpForm[0].reportValidity();
        }
      });
    });
  }

  checkLoggedIn(function (loginResponse) {
    isLoggedIn = loginResponse.isLoggedIn;
    isAdmin = loginResponse.isAdmin;

    if (isLoggedIn) {
      $("#loginDiv").hide();
      $("#loggedInDiv").show();
    } else {
      $("#loginDiv").show();
      $("#loggedInDiv").hide();
    }
  });

  setupDynamicNav();

  $(document).off("click", "#btnLogout").on("click", "#btnLogout", function () {
    $.ajax({
      url: "/api/v1/logout",
      method: "GET",
      dataType: "json",
    }).then(
      function (cmdResponse) {
        if (cmdResponse.code == "0") {
          $("#loginDiv").show();
          $("#loggedInDiv").hide();
          setupDynamicNav();
        } else {
          $("#logOutErrorAlert").text(cmdResponse.message);
          $("#logOutErrorAlert").removeClass("collapse");
        }
      },
      function (fail) {
        var respText = fail.responseText;
        $("#logOutErrorAlert>strong").text(respText);
        $("#logOutErrorAlert").removeClass("collapse");
      }
    );
  });

  $(document).off("click", "#btnlogin").on("click", "#btnlogin", function () {
    var email = $("#loginUsername").val();
    var pw = $("#loginPassword").val();
    var postLoginData = JSON.stringify({
      email: email,
      password: pw,
    });
    $.ajax({
      url: "/api/v1/login",
      method: "POST",
      data: postLoginData,
      dataType: "json",
    }).then(
      function (cmdResponse) {
        if (cmdResponse.code == "0") {
          $("#errorAlert").addClass("collapse");
          $("#logInAlert").removeClass("collapse");
          $("#loginDiv").hide();
          $("#loggedInDiv").show();
          setupDynamicNav();
        } else if (cmdResponse.code == "1") {
          $("#logInErrorAlert").removeClass("collapse");
        } else {
          $("#logInErrorAlert").text(cmdResponse.message);
          $("#logInErrorAlert").removeClass("collapse");
        }
      },
      function (fail) {
        var respText = fail.responseText;
        $("#logInErrorAlert>strong").text(respText);
        $("#logInErrorAlert").removeClass("collapse");
      }
    );
  });
}

function setupDynamicNav() {
  $(".dynamic-nav-item").remove();
  isAdmin = null;
  isLoggedIn = null;

  getIsAdmin(function (isAdmin) {
    if (isAdmin) {
      $("ul.navbar-nav").append(
        '<li class="nav-item dynamic-nav-item"><a class="nav-link" href="wirocpython2releases.html">WiRocPython2 Releases</a></li>' +
        '<li class="nav-item dynamic-nav-item"><a class="nav-link" href="wirocbleapireleases.html">WiRocBLEAPI Releases</a></li>' +
        '<li class="nav-item dynamic-nav-item"><a class="nav-link" href="wirocpython2releaseupgradescripts.html">WiRocPython2 Upgrade Scripts</a></li>' +
        '<li class="nav-item dynamic-nav-item"><a class="nav-link" href="wirocbleapireleaseupgradescripts.html">WiRocBLEAPI Upgrade Scripts</a></li>'
      );
    }

    if (isLoggedIn) {
      $("ul.navbar-nav").append(
        '<li class="nav-item dynamic-nav-item"><a class="nav-link" href="deviceaccess.html">Device Access</a></li>' +
        '<li class="nav-item dynamic-nav-item"><a class="nav-link" href="analyze.html">Analyze Logs</a></li>'
      );
    }

    $.each($(".navbar").find(".nav-link"), function () {
      $(this).toggleClass(
        "active",
        window.location.pathname.indexOf($(this).attr("href")) > -1
      );
    });
  });
}
