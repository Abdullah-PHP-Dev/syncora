$(document).ready(function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
  
    $('#hidden_update').html('');
    $(document).on('submit', '#api', function (e) {
        e.preventDefault();
        e.stopPropagation();
      
        $('#hidden_update').html('');
        let form = $(this);
        let api = form.serialize();
        var key = $('#key').val();
        let method = "POST";
        let mode = $('#form_mode').val();

        if (mode === "update") {
            apiUrl = updateAPIUrl.replace(':API', $('#api_id').val());
            method = "PUT"; // or PATCH
        }
        Swal.fire({
            title: saveDescription,
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: saveHeader,
            denyButtonText: dontSave
        }).then((result) => {

            if (result.isConfirmed) {

                $.ajax({
                    method: method,
                    url: apiUrl,
                    data: api,

                    success: function (res) {
                        let setting = res.data;

                        if (!setting) {
                            Swal.fire("Error", "Invalid response from server", "error");
                            return;
                        }
                        if (mode === "update") {
                            // update row instead of append
                            location.reload(); // or update DOM dynamically
                        } else {
                            let row = `
                            <tr>
                                <td>${key}</td>
                                <td>
                                    <span class="badge bg-label-success">
                                        ${setting}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="dropdown">
                                            <a href="javascript:;" class="btn dropdown-toggle hide-arrow p-0" data-bs-toggle="dropdown">
                                                <i class="icon-base bx bx-dots-vertical-rounded icon-md text-body"></i>
                                            </a>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <a href="javascript:void(0);" data-key="${key}" class="dropdown-item edit"> ${edit}</a>
                                                <div class="dropdown-divider"></div>
                                                <a href="javascript:;" data-key="${key}" class="dropdown-item delete-record text-danger">${deletebutton}</a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        `;

                            $('#apiTable').append(row);

                            form[0].reset();
                            $('.error-message').html('');
                            $('.form-control').removeClass('is-invalid');
                            $('#apiModal').modal('hide');

                            Swal.fire("Success", "", "success");
                        }

                    },

                    error: function (xhr) {

                        $('.error-message').html('');
                        $('.form-control').removeClass('is-invalid');

                        if (xhr.status === 422) {

                            let errors = xhr.responseJSON.errors;

                            $.each(errors, function (field, messages) {
                                $('.error-' + field).html(messages[0]);
                                $('input[name="' + field + '"]').addClass('is-invalid');
                            });

                        } else {
                            Swal.fire("Error!", "Something went wrong", "error");
                        }
                    }
                });

            } else if (result.isDenied) {
                Swal.fire("Changes not saved", "", "info");
            }
        });
    });

    $(document).on('click', '.edit', function (e) {
        e.preventDefault();
        let key = $(this).data('key');
        // IMPORTANT: do not overwrite global variable
        let url = getAPIUrl.replace(':API', key);
        $.ajax({
            method: 'GET',
            url: url,

            success: function (res) {
                let setting = res.data;
                console.log(setting);
                if (!setting) {
                    Swal.fire("Error", "Invalid response from server", "error");
                    return;
                }

                $('#key').val(key);
                $('#api_id').val(key);
                $('#form_mode').val('update'); // 🔥 switch mode
                $('#hidden_update').html('<input type="hidden" value="hidden" name="hidden_update" class="hidden_update">');
                $('#value').val(setting);

                // correct modal id
                $('#apiModal').modal('show');
            },

            error: function (xhr) {

                $('.error-message').html('');
                $('.form-control').removeClass('is-invalid');

                if (xhr.status === 422) {

                    let errors = xhr.responseJSON.errors;

                    $.each(errors, function (field, messages) {
                        $('.error-' + field).html(messages[0]);
                        $('input[name="' + field + '"]').addClass('is-invalid');
                    });

                } else {
                    Swal.fire("Error!", "Something went wrong", "error");
                }
            }
        });
    });

    $(document).on('click', '.delete-record', function (e) {
        e.preventDefault();

        let key = $(this).data('key');

        let row = $(this).closest('tr'); // store row reference

        // never mutate global URL
        let url = destroyAPIUrl.replace(':API', key);
  
        Swal.fire({
            title: areYouSure,
            text: YouWontBeAbleToRevertThis,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: YesDeleteIt
        }).then((result) => {

            if (result.isConfirmed) {

                $.ajax({
                    method: 'DELETE',
                    url: url,

                    success: function (res) {

                        Swal.fire({
                            title: "Deleted!",
                            text: "Record has been deleted.",
                            icon: "success"
                        });

                        // remove row safely
                        row.remove();
                    },

                    error: function (xhr) {

                        Swal.fire(
                            "Error!",
                            "Something went wrong while deleting.",
                            "error"
                        );
                    }
                });
            }
        });
    });

    // Pause/reactivate a campaign in place, without navigating away or
    // removing the row - mirrors the .delete-record pattern above but PATCHes
    // instead of deleting. Toggles its own data-status/label so clicking it
    // again reverses the action, and updates the status badge in that row.
    $(document).on('click', '.status-toggle-record', function (e) {
        e.preventDefault();

        if (typeof statusAPIUrl === 'undefined') return;

        let $link = $(this);
        let key = $link.data('key');
        let targetStatus = $link.data('status');
        let row = $link.closest('tr');
        let url = statusAPIUrl.replace(':API', key);

        $.ajax({
            method: 'PATCH',
            url: url,
            data: { status: targetStatus },

            success: function () {
                let isNowActive = targetStatus === 'ACTIVE';

                row.find('.status-badge')
                    .toggleClass('bg-label-success', isNowActive)
                    .toggleClass('bg-label-secondary', !isNowActive)
                    .text(isNowActive ? 'Active' : 'Paused');

                $link.data('status', isNowActive ? 'PAUSED' : 'ACTIVE');
                $link.text(isNowActive ? 'Pause' : 'Activate');

                Swal.fire({
                    icon: 'success',
                    title: 'Updated',
                    text: isNowActive ? 'Campaign activated.' : 'Campaign paused.',
                    timer: 1500,
                    showConfirmButton: false,
                });
            },

            error: function (xhr) {
                Swal.fire(
                    "Error!",
                    xhr.responseJSON?.error || xhr.responseJSON?.message || "Something went wrong while updating status.",
                    "error"
                );
            }
        });
    });

    $(document).on('submit', '#campaign',function (e){
        e.preventDefault();
        $('.error-message').html('');
        var formData = new FormData(this);
        if (method === 'PUT') {
            formData.append('_method', 'PUT');
        }

        $.ajax({
            url: url,
            type: 'POST',          // ALWAYS POST
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json",
            success: function(response) {
                if (response.success === true){
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message || 'Campaign has been launched successfully.',
                        showConfirmButton: true,
                    }).then(() => {
                    window.location.href = redirectUrl;
                });
                } else if (response.success === false && response.message === "No active token found.") {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Something went wrong!',
                        showConfirmButton: true,
                    }).then(() => {
                        window.location.href = redirectUrl;
                    });
                } else if (response.success === false && response.message !== "No active token found.") {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || response.error || 'Something went wrong!',
                    });
                    $('#responseMessage').html('<li class="text-red-800" style="color:red">' + response.message + '</li>');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || response.error,
                    });
                    $('#responseMessage').html('<li class="text-red-800" style="color:red">' + response.message + '</li>');
                }
            },
            error: function(xhr) {
                Swal.close();
                console.log(xhr, xhr.responseJSON);
                if (xhr.status === 422) { // Laravel validation error status code
                    let errors = xhr.responseJSON.data ?? xhr.responseJSON.errors;
                    console.log(errors);
                    $.each(errors, function (field, messages) {
                        $('.error-' + field).text(messages[0]); // Display first error message
                    });

                    // Multi-step pages (eg. the Facebook wizard) hide every section
                    // but the current one, so an error field set above can end up
                    // inside a display:none step - invisible even though it's
                    // populated. Let the page itself react (jump to the right step,
                    // flag the step pill) since only it knows its own step layout;
                    // this form is shared across platforms that don't have steps.
                    $(document).trigger('campaign:validationErrors', [errors]);

                    const rawError = xhr.responseJSON?.error;

                    const errorMessage =
                        typeof rawError === 'string' && rawError.trim() !== ''
                            ? rawError
                            : 'Please fix the highlighted errors.';
        
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: errorMessage,
                    });
                } else {
                    let message =
                    xhr.responseJSON?.message ||
                    xhr.responseJSON?.error ||
                    'Something went wrong';

                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: message,
                    });

                    $('#responseMessage').html(
                        '<li class="text-red-800" style="color:red">' +
                        message +
                        '</li>'
                    );
                }
            }
        });
    });
});