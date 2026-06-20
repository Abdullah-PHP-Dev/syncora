$(document).ready(function () {
    $(document).on('submit', '#profile', function (e) {
        e.preventDefault();

        var profile = $(this).serialize();

        Swal.fire({
            title: saveDescription,
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: saveHeader,
            denyButtonText: dontSave
        }).then((result) => {

            if (result.isConfirmed) {

                $.ajax({
                    method: 'PUT', // important (see note below)
                    url: updateUrl,
                    data: profile,
                    success: function (res) {
                        Swal.fire(saveHeader+"!", "", "success");
                    },
                    error: function (xhr) {

                        $('.error-message').html('');
                        $('.form-control').removeClass('is-invalid');
                    
                        if (xhr.status === 422) {
                    
                            let errors = xhr.responseJSON.errors;
                    
                            $.each(errors, function (field, messages) {
                    
                                // Add error text
                                $('.error-' + field).html(messages[0]);
                    
                                // Add invalid class to input
                                $('input[name="' + field + '"]')
                                    .addClass('is-invalid');
                    
                            });
                    
                        } else {
                    
                            Swal.fire(error+"!", wentWrong, "error");
                    
                        }
                    }
                });

            } 
            else if (result.isDenied) {
                Swal.fire(changesNotSaved, "", "info");
            }
        });
    });
});