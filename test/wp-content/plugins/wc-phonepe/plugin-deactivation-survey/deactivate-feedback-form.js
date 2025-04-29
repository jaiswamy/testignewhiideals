(function($) {

    if (!window.codecabin)
        window.codecabin = {};

    if (codecabin.DeactivateFeedbackForm)
        return;

    codecabin.DeactivateFeedbackForm = function(plugin) {
        var self = this;
        var strings = sgits_deactivate_feedback_form_strings;
        this.plugin = plugin;

        // Dialog HTML
        var element = $('\
			<div class="sgits-deactivate-dialog" data-remodal-id="' + plugin.slug + '">\
				<form>\
					<input type="hidden" name="plugin"/>\
					<h2>' + strings.quick_feedback + '</h2>\
					<p>\
						' + strings.foreword + '\
					</p>\
					<ul class="sgits-deactivate-reasons"></ul>\
					<textarea rows="4" name="message" placeholder="' + strings.brief_description + '"></textarea>\
					<br>\
					<p>We are not collecting any sensitive data. :) Trust us. <a href="https://sevengits.com/sevengits-usage-analytics/" target="_blank">Learn more</a>\
					<p class="sgits-deactivate-dialog-buttons">\
						<input type="submit" class="button confirm" value="' + strings.skip_and_deactivate + '"/>\
						<button data-remodal-action="cancel" class="button button-primary">' + strings.cancel + '</button>\
					</p>\
				</form>\
			</div>\
		')[0];
        this.element = element;

        $(element).find("input[name='plugin']").val(JSON.stringify(plugin));

        $(element).on("change", "input[name='reason']", function(event) {

            $(element).find("input[type='submit']").val(
                strings.submit_and_deactivate
            );
        });

        $(element).find("form").on("submit", function(event) {
            self.onSubmit(event);
        });

        // Reasons list
        var ul = $(element).find("ul.sgits-deactivate-reasons");
        for (var key in plugin.reasons) {
            var li = $("<li><label><input type='radio' name='reason'/> <span></span></label></li>");

            $(li).find("input").val(key);
            $(li).find("span").html(plugin.reasons[key]);

            $(ul).append(li);
        }

        // Listen for deactivate
        $("#the-list [data-slug='" + plugin.slug + "'] .deactivate>a").on("click", function(event) {
            self.onDeactivateClicked(event);
        });
    }

    codecabin.DeactivateFeedbackForm.prototype.onDeactivateClicked = function(event) {
        this.deactivateURL = event.target.href;

        event.preventDefault();

        if (!this.dialog)
            this.dialog = $(this.element).remodal();
        this.dialog.open();
    }

    codecabin.DeactivateFeedbackForm.prototype.onSubmit = function(event) {
        var element = this.element;
        var strings = sgits_deactivate_feedback_form_strings;
        var self = this;
        var data = $(element).find("form").serialize();

        $(element).find("button, input[type='submit']").prop("disabled", true);

        const submit_btn = $(element).find("input[type='submit']");
        if ($(element).find("input[name='reason']:checked").length) {
            event.preventDefault();
            submit_btn.siblings().hide();
            submit_btn.val(strings.please_wait);

            $.ajax({
                type: "POST",
                url: "https://sevengits.com/wp-json/route/analytics/plugin-feedback",
                data: data,
                complete: function() {
                    submit_btn.val(strings.thank_you);
                    window.location.href = self.deactivateURL;
                }
            });
        } else {
            submit_btn.val(strings.please_wait);
            window.location.href = self.deactivateURL;
        }

        event.preventDefault();
        return false;
    }

    $(document).ready(function() {

        for (var i = 0; i < sgits_deactivate_feedback_form_plugins.length; i++) {
            var plugin = sgits_deactivate_feedback_form_plugins[i];
            new codecabin.DeactivateFeedbackForm(plugin);
        }

    });

})(jQuery);