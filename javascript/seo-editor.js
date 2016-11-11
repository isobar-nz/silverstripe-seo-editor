(function ($) {

    $.entwine('ss', function ($) {

        $('.ss-seo-editor .ss-gridfield-item input, .ss-seo-editor .ss-gridfield-item textarea').entwine({

            onchange: function () {

                // kill the popup for form changes
                window.onbeforeunload = null;

                var $this = $(this);
                var id = $this.closest('tr').attr('data-id');
                var preURL = $this.closest('.ss-gridfield').attr('data-url');
                var url;

                if(preURL.indexOf('?') != -1)
                    url = preURL.substr(0, preURL.indexOf('?')) + "/update/" + id + preURL.substr(preURL.indexOf('?'));
                else
                    url = preURL + "/update/" + id;
                    
                var data = $this.attr('name') + '=' + $(this).val();

                statusMessage('Saving changes', 'good');

                $.post(
                    url,
                    data,
                    function (data, textStatus) {
                        statusMessage(data.message, data.type);

                        $this.closest('td').removeClass();
                        if (data.errors.length) {
                            $this.closest('td').addClass('seo-editor-error');
                            data.errors.forEach(function(error) {
                                $this.closest('td').addClass(error)
                            });
                        } else {
                            $this.closest('td').addClass('seo-editor-valid');
                        }

                    },
                    'json'
                );
            }
        });

    });

}(jQuery));
