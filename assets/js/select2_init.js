/**
 * Global Select2 with search on every dropdown
 */
(function ($) {
    if (!($ && $.fn && $.fn.select2)) {
        return;
    }

    function placeholderOf($el) {
        var blank = $el.find('option[value=""]').first().text();
        if (blank) {
            return blank.replace(/^--\s*/, '').replace(/\s*--$/, '').trim() || 'Select';
        }
        return 'Search & select';
    }

    function focusOpenSearch() {
        var field = document.querySelector('.select2-container--open .select2-search__field');
        if (!field) return;
        field.setAttribute('placeholder', 'Type to search…');
        try {
            field.focus({ preventScroll: true });
        } catch (e) {
            field.focus();
        }
    }

    function bindSelect2($el) {
        if (!$el.length || $el.hasClass('no-select2') || $el.data('select2')) {
            return;
        }

        var hasBlank = $el.find('option[value=""]').length > 0;
        var inLength = $el.closest('.dataTables_length').length > 0;
        $el.select2({
            width: inLength ? 'style' : '100%',
            placeholder: placeholderOf($el),
            allowClear: hasBlank && !$el.prop('required'),
            minimumResultsForSearch: inLength ? Infinity : 0,
            dropdownParent: $(document.body),
            language: {
                noResults: function () { return 'No matching option'; },
                searching: function () { return 'Searching…'; }
            }
        });
    }

    // Select2 hides the native <select> with aria-hidden; never leave focus on it
    $(document).on('select2:opening select2:open', function (e) {
        var el = e.target;
        if (el && el.blur && document.activeElement === el) {
            el.blur();
        }
        focusOpenSearch();
        setTimeout(focusOpenSearch, 0);
    });

    $(document).on('focusin', 'select.select2-hidden-accessible', function (e) {
        var $sel = $(this);
        var data = $sel.data('select2');
        if (!data) return;
        e.preventDefault();
        this.blur();
        if (data.isOpen && data.isOpen()) {
            focusOpenSearch();
        } else if (data.$selection && data.$selection.length) {
            data.$selection.trigger('focus');
        }
    });

    function initAllSelects(root) {
        $(root).find('select').each(function () {
            bindSelect2($(this));
        });
    }

    $(function () {
        initAllSelects(document);

        // DataTables length dropdown is created after DataTable() init
        if ($.fn.DataTable) {
            $(document).on('init.dt draw.dt', function (e) {
                var $wrap = $(e.target).closest('.dataTables_wrapper');
                if ($wrap.length) {
                    initAllSelects($wrap);
                }
            });
        }
    });
})(window.jQuery);
