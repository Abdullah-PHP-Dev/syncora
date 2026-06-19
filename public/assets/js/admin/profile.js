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
                        console.log(res);

                        Swal.fire(saveHeader+"!", "", "success");
                    },
                    error: function (xhr) {
                        console.log(xhr.responseText);

                        Swal.fire(error+"!", wentWrong, "error");
                    }
                });

            } 
            else if (result.isDenied) {
                Swal.fire(changesNotSaved, "", "info");
            }
        });
    });
});