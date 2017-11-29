/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined) {
    "use strict";

    // ################################## REACTION HANDLER ###########################################

    var clickHandler = function() {
        var clickables = $('.th_reactions__trigger');
        clickables.off('click');
        clickables.click(function(e) {
            var ele = $(this).closest('.reactions-right').find('.reactions-right__list');
            if (ele.hasClass('reactions-bar--show')) {
                ele.removeClass('reactions-bar--show');
                void ele.width();
                ele.addClass('reactions-bar--hide');
            } else {
                ele.removeClass('reactions-bar--hide');
                void ele.width();
                ele.addClass('reactions-bar--show');
            }
        })
    }

    XF.ReactClick = XF.Click.newHandler({
        eventNameSpace: 'XFReactClick',

        options: {
			container: '.reactions-bar',
			target: null,
			href: null
        },

		$loaderTarget: null,
		$container: null,
		href: null,
		loading: false,

        init: function() {
			var container = this.options.container,
				$container = container ? this.$target.closest(container) : this.$target,
				target = this.options.target,
				$target = target ? XF.findRelativeIf(target, this.$container) : $container;

			this.$container = $container;

			if (!this.options.href) {
				this.href = this.$target.attr('href');
			}

			if (!this.href) {
				console.error('No reaction href for %o', this.$target);
			}
        },

        click: function(e) {
            e.preventDefault();
			$('.tooltip').hide();

			if (this.loading) {
				return;
			}

			this.loading = true;

			var t = this;
            XF.ajax('POST', this.href, null, $.proxy(this, 'handleAjax'),
				{
					skipDefaultSuccess: true
				}
			).always(function() {
				t.loading = false;
			});;
        },

        handleAjax: function(data) {
			var $container = this.$container;
            if (typeof data.html !== 'undefined' && this.$container.length) {
                if (data.html.content) {
                    XF.setupHtmlInsert(data.html, function($html, container) {
						$container.html($html);
                        clickHandler();
                    });
                }
            }
        }
    });

    XF.Click.register('react', 'XF.ReactClick');

    $(document).ready(function() {
        $('.th_reactions__trigger').hover(function(e) {
            $(this).removeClass('th_reactions__trigger--hoverOut').addClass('th_reactions__trigger--hoverIn');
        }, function(e) {
            var ele = $(this);
            ele.removeClass('th_reactions__trigger--hoverIn').addClass('th_reactions__trigger--hoverOut');
            window.setTimeout(function() {
                ele.removeClass('th_reactions__trigger--hoverOut');
            }, 300);
        });

        clickHandler();
    })
}(jQuery, window, document);
