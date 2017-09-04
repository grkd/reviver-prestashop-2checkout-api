<script type="text/javascript" src="{$modules_dir}twocheckoutreviver/assets/jquery.blockUI.js"></script>
<script type="text/javascript"src="https://www.2checkout.com/checkout/api/2co.min.js"/></script>
<div class="payment_module" style="padding: 0 20px 20px;border-bottom: 1px solid #ccc;margin-bottom: 20px;">
    <p id="twocheckout-error" style="border: 1px solid red; padding: 0.6em; margin-bottom: 0.7em; color: red; background: #FFF">{l s='Payment Authorization Failed: Please verify your Credit Card details are entered correctly and try again, or try another payment method.' mod='twocheckoutreviver'}</p>
    <h3 class="twocheckout_title" style="margin-bottom: 20px;margin-top:5px;">{l s='Pay by credit card with our secured payment server' mod='twocheckoutreviver'}</h3>
    <div class="error" {if $authfailed=='show'}style="display:block;border: 1px solid red; padding: 0.6em; margin-bottom: 0.7em; color: red; background: #FFF"{else}style="display:none;border: 1px solid red; padding: 0.6em; margin-bottom: 0.7em; color: red; background: #FFF"{/if} id="twocheckout_error_creditcard">
    <p style="margin:0">{l s='Payment Authorization Failed: Please verify your Credit Card details are entered correctly and try again, or try another payment method.' mod='twocheckoutreviver'}</p>
    </div>
    <form action="{$link->getModuleLink('twocheckoutreviver', 'validation', [], true)}" method="POST" id="twocheckoutCCForm" onsubmit="return false">
        <input id="sellerId" type="hidden" value="{$twocheckout_sid}">
        <input id="publishableKey" type="hidden" value="{$twocheckout_public_key}">
        <input id="token" name="token" type="hidden" value="">
        <div class="block-left">
            <label>{l s='Card Number' mod='twocheckoutreviver'}</label><br />
            <input class="numeric" type="text" size="20" autocomplete="off" id="ccNo" style="width: 210px; border: #CCCCCC solid 1px; padding: 3px;" required/>
        </div>
        <br />
        <div class="block-left">
            <label>{l s='Expiration (MM/YYYY)' mod='twocheckoutreviver'}</label><br />
            <select id="expMonth" name="month" style="border: #CCCCCC solid 1px; padding: 3px;" required>
                <option value="01">{l s='January' mod='twocheckoutreviver'}</option>
                <option value="02">{l s='February' mod='twocheckoutreviver'}</option>
                <option value="03">{l s='March' mod='twocheckoutreviver'}</option>
                <option value="04">{l s='April' mod='twocheckoutreviver'}</option>
                <option value="05">{l s='May' mod='twocheckoutreviver'}</option>
                <option value="06">{l s='June' mod='twocheckoutreviver'}</option>
                <option value="07">{l s='July' mod='twocheckoutreviver'}</option>
                <option value="08">{l s='August' mod='twocheckoutreviver'}</option>
                <option value="09">{l s='September' mod='twocheckoutreviver'}</option>
                <option value="10">{l s='October' mod='twocheckoutreviver'}</option>
                <option value="11">{l s='November' mod='twocheckoutreviver'}</option>
                <option value="12">{l s='December' mod='twocheckoutreviver'}</option>
            </select>
            <span> / </span>
            <select id="expYear" name="year" style="border: #CCCCCC solid 1px; padding: 3px;" required>
                <option value="2015">2015</option>
                <option value="2016">2016</option>
                <option value="2017">2017</option>
                <option value="2018">2018</option>
                <option value="2019">2019</option>
                <option value="2020">2020</option>
                <option value="2021">2021</option>
                <option value="2022">2022</option>
                <option value="2023">2023</option>
                <option value="2024">2024</option>
                <option value="2025">2025</option>
                <option value="2026">2026</option>
                <option value="2027">2027</option>
                <option value="2028">2028</option>
                <option value="2029">2029</option>
                <option value="2030">2030</option>
            </select>
        </div>
        <br />
        <div class="block-left">
            <label>{l s='CVC' mod='twocheckoutreviver'}</label><br />
            <input class="numeric" id="cvv" type="text" size="4" autocomplete="off"  style="border: #CCCCCC solid 1px; padding: 3px;" required />
        </div>
        <br />
        <input type="submit" class="button" value="{l s='Submit Payment' mod='twocheckoutreviver'}" style="background: #000;text-transform: uppercase;border: none;width: 200px;padding: 0;height: 35px;font-size: 15px;"/>
        <a href="{$link->getPageLink('order', true, NULL, "step=3")}" class="rother-methods" style="background: #fff;text-transform: uppercase;border: 3px solid #000;width: 230px;padding: 0;font-size: 15px;display: inline-block;line-height: 29px;margin-left: 15px;font-weight: 700;color: #000;text-align: center;border-radius: 3px;">{l s='Other payment methods' mod='checkout'}</a>
        <div class="block-right">
            <img src="{$modules_dir}twocheckoutreviver/assets/credit-cards.png" />
        </div>
    </form>
</div>

<script type="text/javascript">

  function successCallback(data) {
    $.blockUI({ message: '<p><h1>Just a moment while we process your payment...</h1></p>' });
    var myForm = document.getElementById('twocheckoutCCForm');
    myForm.token.value = data.response.token.token;
    myForm.submit();        
  }

  function errorCallback(data) {
    clearFields(); 
    if (data.errorCode === 200) {
      TCO.requestToken(successCallback, errorCallback, 'tcoCCForm');
    } else if(data.errorCode == 401) {
      $("#twocheckout_error_creditcard").show();
    } else {
      alert(data.errorMsg);
    } 
  }

  $("#twocheckoutCCForm").submit(function (e) {
    e.preventDefault();
    $("#twocheckout_error_creditcard").hide();
    TCO.requestToken(successCallback, errorCallback, 'twocheckoutCCForm');
  });

  (function($) {
    $.QueryString = (function(a) {
      if (a == "") return {};
      var b = {};
      for (var i = 0; i < a.length; ++i)
        {
          var p=a[i].split('=');
          if (p.length != 2) continue;
          b[p[0]] = decodeURIComponent(p[1].replace(/\+/g, " "));
        }
        return b;
    })(window.location.search.substr(1).split('&'))
  })(jQuery);
  if ($.QueryString["twocheckouterror"]) {
    $( "#twocheckout-error" ).show();
  } else {
      $( "#twocheckout-error" ).hide();
  }

  $('.numeric').on('blur', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
  });

  function clearFields () {
    $('#ccNo').val('');
    $('#expMonth').val('');
    $('#expYear').val('');
    $('#cvv').val('');
  }

</script>
