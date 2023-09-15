(function($) {

    // bulk check
    $('#bulk-check').on('click', function(e) {
        e.preventDefault();

        var nonce = $('#nonce').val();

        $.ajax({
            type: "post",
            dataType: "json",
            url: $('#ajax').val(),
            data: {
                action: 'bulk-check',
                nonce: nonce
            },
            success: function(response) {
                if (response) {
                    console.log(response);
                }
            }
        });
    });

    // show / hide images with no issues
    $('#show-only-issues').on('click', function() {
        var _this = $(this);
        var showing_only_issues = $(this).attr('filter-active') || 0;
        var images = $('.image-code');

        console.log(showing_only_issues);

        if (showing_only_issues == 0) {
            $(images).each(function() {
                if (!$(this).find('.image-details-grid').hasClass('has-notice')) {
                    $(this).hide();
                }
            });
            $(_this).attr('filter-active', 1);
        } else {
            $(images).each(function() {
                if (!$(this).find('.image-details-grid').hasClass('has-notice')) {
                    $(this).show();
                }
            });
            $(_this).attr('filter-active', 0);
        }
    });

    // show / hide images that are too small to create additional versions for
    $('#show-unqualified').on('click', function() {
        var state = $(this).attr('data-state');
        toggle_unqualified(state);
    });

    function toggle_unqualified(curr_state) {
        var images = $('[data-qualified]');

        if (curr_state === 'show') {
            $(images).each(function() {
                if ($(this).attr('data-qualified') == '') {
                    $(this).hide();
                }
            });

            $('#show-unqualified').attr('data-state', 'hide');
        } else {
            $(images).each(function() {
                if ($(this).attr('data-qualified') == '') {
                    $(this).show();
                }
            });

            $('#show-unqualified').attr('data-state', 'show');
        }
    }
    toggle_unqualified('show');
})(jQuery);




function copyToClipboard(elem) {
    // add 'active' classs to the element that was clicked
    elem.classList.add('copied');
    let originalText = elem.textContent;
    elem.textContent = 'Copied!';

    setTimeout(function() {
        elem.classList.remove('copied');
        elem.textContent = originalText;
    }, 500);

    // Create an auxiliary hidden input
    var aux = document.createElement("input");
    // Get the text from the element passed into the input
    aux.setAttribute("value", elem.getAttribute('data-copy-value'));
    // Append the aux input to the body
    document.body.appendChild(aux);
    // Highlight the content
    aux.select();
    // Execute the copy command
    document.execCommand("copy");
    // Remove the input from the body
    document.body.removeChild(aux);
}

document.addEventListener('DOMContentLoaded', function() {
  let imageToolsWrapper = document.getElementById('image-tools-wrapper');
  let prompt = document.getElementById('mj-prompt-input');
  let nPrompt = document.getElementById('mj-negative-prompt-input');
  let ar = document.getElementById('mj-ar-input');

  function checkJobStatusRecursive(job_id) {
      let data = new FormData();
      data.append('job_id', job_id);
      let req = new XMLHttpRequest();

      req.onreadystatechange = function() {
          if (this.readyState == 4 && this.status == 200) {
              let response = JSON.parse(this.responseText);

              // accepted = Job has been successfully submitted
              // queuing = Job is in queue waiting to start
              // processing = Job is being processed
              // success = Job has completed successfully
              // failed = Job has failed. A failed response will include failure reason

              if (response.status === 'accepted' || response.status === 'queuing' || response.status === 'processing') {
                  console.log('Job is still in progress. Checking again...');

                  // Call the function recursively to check again
                  setTimeout(function() {
                      checkJobStatusRecursive(job_id);
                  }, 300000); // 5 minutes

              } else {

                  console.log(response);

              }
          }
      };

      req.open("POST", "<?php echo admin_url( 'admin-ajax.php' ); ?>?action=media_optimization_upscale_check", true);
      req.send(data);
  }

  // On button clicks
  imageToolsWrapper?.addEventListener('click', function(event) {

      event.preventDefault();
      if (!event.target) return;

      const clickedButton = event.target;
      const action = clickedButton.getAttribute('data-action');

      if (!action) return;

      let formData = new FormData();
      let callback;

      // handle the generation buttons
      if (clickedButton.classList.contains('mj-tool-button')) {
          const wrapperDiv = clickedButton.closest('.mj-generation-wrapper');
          const image = wrapperDiv.querySelector('img');
          const toolButtons = wrapperDiv.querySelectorAll('button');

          // Disable the other two buttons within the wrapper div
          toolButtons.forEach(button => button.disabled = true);

          formData.append('options', JSON.stringify({ 'image_url': image.src, 'filename': image.getAttribute('data-filename') }));

          callback = function(response) {

              if (response.error) {
                  console.log(response.error);
                  alert(response.error);
              }

              if (response.updatedUrl) {
                  const wrapperDiv = clickedButton.closest('.mj-generation-wrapper');
                  const image = wrapperDiv.querySelector('img');
                  const toolButtons = wrapperDiv.querySelectorAll('button');
                  image.src = response.updatedUrl;
              }

              // Update the data-url attribute of all buttons within the wrapper div
              toolButtons.forEach(button => {
                  if (button !== clickedButton) {
                      button.disabled = false;
                  } else {
                      button.textContent = 'Done!';
                  }
              });
          }
      } else {

          switch (action) {
              case "generate":
                  if (!prompt.value) return;

                  let targetElement = document.getElementById('generation-preview');
                  clickedButton.disabled = true;
                  targetElement.classList.add('active');
                  showLoadingAnimation(targetElement);

                  formData.append('options', JSON.stringify({ 'prompt': prompt.value, 'nPrompt': nPrompt.value, 'ar': ar.value }));
                  callback = function(response) {

                      if (response.error) {
                          console.log(response.error);
                          alert(response.error);
                      }

                      if (response.job_id) {
                          let successCallback = function(generationData, remainingCredits) {
                              targetElement.insertAdjacentHTML('afterbegin', generationData.imageGrid);
                              clickedButton.disabled = false;
                              let creditDisplay = document.getElementById('mj-credits');
                              creditDisplay.textContent = 'Credits: ' + remainingCredits;
                              hideLoadingAnimation();
                          }

                          // Call checkGenerationStatus every 20s
                          let statusCheckInterval = setInterval(function() {
                              checkGenerationStatus(response.job_id, successCallback, statusCheckInterval);
                          }, 20000);
                      }
                  }
                  break;
              case "convert-url":
              case "upscale-url":
                  const input = clickedButton.parentElement.querySelector('input');
                  if (!input.value) return;

                  formData.append('options', JSON.stringify({ 'image_url': input.value }));

                  callback = function(response) {

                      if (response.error) {
                          console.log(response.error);
                          alert(response.error);
                      }

                      if (response.updatedUrl) {
                          let copyHtml = `<div class="click-to-copy-wrapper">
                        <p>${response.updatedUrl}</p>
                        <p class="click-to-copy" style="margin-left: 4px;" data-copy-value="${response.updatedUrl}" onclick="copyToClipboard(this);">Click to copy</p>
                      </div>`;
                          clickedButton.insertAdjacentHTML('afterend', copyHtml);
                          input.value = "";
                      }
                  }
                  break;
          }
      }

      formData.append('tool-action', action);
      formData.append('nonce', "<?php echo wp_create_nonce('image_tools_nonce'); ?>");

      performButtonAction(formData, callback);

  });

  let aceInput = document.querySelector('input[name="media_optimization_sizes"]');
  if( aceInput ) {
    let editor = ace.edit("media_sizes_editor");
    editor.setTheme("ace/theme/monokai");
    editor.session.setMode("ace/mode/json");
    editor.session.setValue(aceInput.value); // grab input value to load

    editor.session.on('change', function(delta) {
      debounce( aceInput.value = editor.getSession().getValue() );
    });
  }

  let aceInput1 = document.querySelector('input[name="mediacraft_settings"]');
  if( aceInput1 ) {
    let editor1 = ace.edit("site_settings_editor");
    editor1.setTheme("ace/theme/monokai");
    editor1.session.setMode("ace/mode/json");
    editor1.session.setValue(aceInput1.value); // grab input value to load

    editor1.session.on('change', function(delta) {
      debounce( aceInput1.value = editor1.getSession().getValue() );
    });
  }

  // action buttons
  let actions = document.querySelectorAll('a[data-action]');
  if( actions ) {
    actions.forEach( el => {
      el.addEventListener('click', function(e) {
        e.preventDefault();

        const action = el.getAttribute('data-action');

        let formData = new FormData();
        let callback;

        switch (action) {
          case "regenerate":
          case "checkSizes":

            formData.append('options', JSON.stringify({ 'imageID': el.getAttribute('data-imageID') }));

            callback = function(response) {
              if (response.error) {
                console.log(response.error);
                alert(response.error);
              }

              if (response.sizeAnalysis) {
                console.log(response.sizeAnalysis);
              }
            }
            break;
        }

        formData.append('tool-action', action);
        formData.append('nonce', mediacraft_ajax.nonce);

        performButtonAction(formData, callback);
      })
    })
  }
});

function checkGenerationStatusDeprecated(job_id, callback, interval) {
    let req = new XMLHttpRequest();

    req.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            let response = JSON.parse(this.responseText);

            if (response.status === 'completed') {
                // Generation task is completed
                // Update the UI with the generated image or result
                clearInterval(interval);
                callback(response.generationData, response.remaining_credits);

            } else if (response.status === 'in_progress') {
                // Generation task is still in progress
                // Update the UI to show a loading spinner or progress bar
            }
        }
    };

    let rest_url = "<?php echo rest_url('generation/v1/generation/status/'); ?>";
    // rest_url('wp/v2/posts/{$post_id}/');

    req.open("GET", rest_url + job_id, true);
    req.send();
}

function performButtonAction(formData, callback) {

  let req = new XMLHttpRequest();

  req.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
      let response = JSON.parse(this.responseText);

      callback(response);
    }
  };

  req.open("POST", mediacraft_ajax.ajax_url + "?action=mediacraft_tools", true);
  req.send(formData);
}

// Function to show the loading animation
function showLoadingAnimation(targetEl) {
    // Create an SVG element for the loading animation
    var loadingSvg = document.createElement("div");
    loadingSvg.setAttribute("class", "loading");
    loadingSvg.innerHTML = `<p style="margin: 0 0 8px;">Generating.. please be patient</p><span><i></i><i></i><i></i></span>`;

    // Add a unique ID to the SVG element to easily identify it
    loadingSvg.setAttribute("id", "loadingAnimation");

    // Append the loading animation
    targetEl.insertAdjacentElement("afterbegin", loadingSvg);
}

// Function to hide the loading animation
function hideLoadingAnimation() {
    // Get the loading animation element
    var loadingSvg = document.getElementById("loadingAnimation");

    // Check if the loading animation exists
    if (loadingSvg) {
        // Remove the loading animation element
        loadingSvg.remove();
    }
}

function debounce(func, timeout = 300){
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => { func.apply(this, args); }, timeout);
  };
}