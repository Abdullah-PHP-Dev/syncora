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
                                                <a href="javascript:void(0);" class="dropdown-item"> ${edit}</a>
                                                <div class="dropdown-divider"></div>
                                                <a href="javascript:;" class="dropdown-item delete-record text-danger">${deletebutton}</a>
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
});