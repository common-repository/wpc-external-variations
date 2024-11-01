'use strict';

(function($) {
  $(document).on('click touch', '.wpcev-btn', function(e) {
    e.preventDefault();

    let url = $(this).data('url');

    if ((url !== undefined) && (url !== '')) {
      if (wpcev_vars.new_tab === 'yes') {
        window.open(url, '_blank').focus();
      } else {
        window.location.href = url;
      }
    }
  });

  $(document).on('found_variation', function(e, t) {
    let variable_id = $(e['target']).
        closest('.variations_form').
        data('product_id');

    if ((t.wpcev_btn !== undefined) && (t.wpcev_btn !== '')) {
      $('.wpcev-variation-add-to-cart-' + variable_id).
          parent().
          find('.woocommerce-variation-add-to-cart').
          hide();
      $('.wpcev-variation-add-to-cart-' + variable_id).
          parent().
          find('.wpcsb-add-to-cart').
          hide();
      $('.wpcev-variation-add-to-cart-' + variable_id).
          html(wpcev_decode_entities(t.wpcev_btn)).show();
    } else {
      $('.wpcev-variation-add-to-cart-' + variable_id).html('').hide();
      $('.wpcev-variation-add-to-cart-' + variable_id).
          parent().
          find('.woocommerce-variation-add-to-cart').
          show();
      $('.wpcev-variation-add-to-cart-' + variable_id).
          parent().
          find('.wpcsb-add-to-cart').
          show();
    }

    $(document.body).trigger('wpcev_found_variation', [t, variable_id]);
  });

  $(document).on('reset_data', function(e) {
    let variable_id = $(e['target']).
        closest('.variations_form').
        data('product_id');

    $('.wpcev-variation-add-to-cart-' + variable_id).html('').hide();
    $('.wpcev-variation-add-to-cart-' + variable_id).
        parent().
        find('.woocommerce-variation-add-to-cart').
        show();
    $('.wpcev-variation-add-to-cart-' + variable_id).
        parent().
        find('.wpcsb-add-to-cart').
        show();

    $(document.body).trigger('wpcev_reset_data', [variable_id]);
  });
})(jQuery);

function wpcev_decode_entities(encodedString) {
  var textArea = document.createElement('textarea');
  textArea.innerHTML = encodedString;

  return textArea.value;
}