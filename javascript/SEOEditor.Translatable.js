/**
 * File: SEOEditor.Translatable.js
 */
(function($) {
	$.entwine('ss', function($){
        /**
		 * whenever a new value is selected, reload the whole CMS in the new locale
		 */
		$('.SEOEditorAdmin #Form_SearchForm_locale').entwine({
			onchange: function(e) {
				// Get new locale code
				locale = {locale: $(e.target).val()};

				// Check existing url
				search = /locale=[^&]*/;
				url = document.location.href;
				if(url.match(search)) {
					// Replace locale code
					url = url.replace(search, $.param(locale));
				} else {
					// Add locale code
					url = $.path.addSearchParams(url, locale);
				}
				$('.cms-container').loadPanel(url);
				return false;
			}
		});

	});
}(jQuery));
