$(document).ready(function () {
    $('#select-user').on('change', function (e) {
        var valueSelected = this.value;
        var validSelection = valueSelected > 0;
        $('#submit-btn').attr('disabled', !validSelection);

        if(validSelection == true) { $("#submit-btn").removeAttr("title") } else { $("#submit-btn").attr('title', 'Vælg først en medarbejder') }
    });
});