 <h5>{{ __('words.select_shipping_method') }}</h5>
 <form action="{{ route('checkout.store', ['user_name' => request('user_name'), 'direct' => true]) }}" id="checkout_form"
     method="post">
     @csrf



     <div class="my-3">
         @if ($shop->store_as_pickup_point)
             <div class="form-check">
                 <input class="form-check-input" type="radio" name="shipping" value=""
                     @if (!old('shipping')) checked @endif id="shipping0" required data-cost="0" />
                 <label class="form-check-label" for="shipping0">{!! strip_tags(__('words.pickup_from_store')) !!}
                 </label>
             </div>
         @endif

         @foreach ($shop->shippings as $shipping)
             <div class="form-check">
                 <input class="form-check-input" name="shipping" type="radio" value="{{ $shipping->id }}"
                     id="shipping{{ $shipping->id }}" data-cost="{{ \Iziibuy::onlyPrice($shipping->costWithTax()) }}"
                     required checked />
                 <label class="form-check-label" for="shipping{{ $shipping->id }}">{{ $shipping->shipping_method }}
                     [ {{ \Iziibuy::price($shipping->costWithTax()) }} ]</label>
             </div>
         @endforeach
         <div class="form-check">
             <input class="form-check-input" type="checkbox" name="terms" value="terms" id="terms" required>
             <label class="form-check-label" for="terms">
                 {!! __('words.external_contract_terms') !!}

                 <a href="#" data-bs-toggle="modal" data-toggle="modal" data-target="#terms-cart-shipping">
                     {{ __('words.betingelser') }}
                 </a>
                 <div class="modal fade" id="terms-cart-shipping" tabindex="10000000" data-bs-backdrop="static"
                     data-bs-keyboard="false" role="dialog" aria-labelledby="modalTitleId" aria-hidden="true">
                     <div class="modal-dialog" role="document" style="max-width: 750px">
                         <div class="modal-content">
                             <div class="modal-body">
                                 <div class="et_pb_text_inner">
                                     <h3>{{ __('words.shop_overview') }}</h3>
                                     <p>{{ __('words.terms_pera2') }}
                                         {{ route('shop.home', request('user_name')) }}.
                                     </p>
                                     <p> <strong>{!! __('words.terms_pera') !!}</strong><br>{{ __('words.terms_pera_3') }}
                                         ({{ route('shop.home', request('user_name')) }})</p>
                                     <p><strong>{{ __('words.terms_pertner_text') }}</strong> </p>
                                     <ul class="ml-5" style="list-style: circle">
                                         <li><b>{{ __('words.shop_company_name') }}:</b> {{ $shop->company_name }}
                                         </li>
                                         <li><b>{{ __('words.invoice_address') }}:</b>
                                             {{ $shop->street }}</li>
                                         <li><b>{{ __('words.invoice_postcode') }}:</b>
                                             {{ $shop->post_code . ' ' . $shop->city }}
                                         </li>
                                         <li><b>{{ __('words.dashboard_complete_reg_form_org_no') }}:</b>
                                             {{ $shop->company_registration }}</li>
                                         <li><b>{{ __('words.invoice_tel') }}:</b> {{ $shop->contact_phone }}</li>
                                         <li><b>{{ __('words.invoice_email') }}:</b> {{ $shop->contact_email }}
                                         </li>
                                     </ul>
                                     {!! $shop->getTranslatedAttribute('terms', app()->getLocale()) !!}

                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>
             </label>
         </div>

         {{-- <input type="checkbox" class="mt-5" required checked>
         {!! __('words.terms') !!} <a href="" data-toggle="modal" data-target="#terms">
             {{ __('words.terms_2') }}</a> <br> --}}
     </div>
     <div class="row">
         <div class="col-12">
             <div class="d-flex flex-column  ">

                 <a class="btn btn-inline-iziibuy mx-2 mb-2" href="{{ route('products', request('user_name')) }}"><i
                         class="fas fa-undo-alt"></i><span>{!! strip_tags(__('words.cart_back_btn')) !!}</span></a>

                 @if (env('MODE') == 'dev')
                     <a class="btn btn-inline-iziibuy btn-block mx-2 "
                         href="{{ route('checkout', [request('user_name'), 'method' => 'two']) }}"><i
                             class="fas fa-check"></i><span>{!! strip_tags(__('words.cart_checkout_company_btn')) !!}</span></a>
                 @endif

             </div>

             <div class="my-3" id="paymentOptions">
                 <h5>{{ __('words.payment_methods') }}</h5>
                 <br>

                 @php
                     $checkoutPaymentOptions = $shop->checkout_payment_methods();
                     $acquirerLabels = app(\App\Services\Checkout\CheckoutPaymentOptionCatalog::class)->acquirerLabels();
                     $paymentOptionIcons = [
                         'visa' => asset('images/payment/visa.png'),
                         'mastercard' => asset('images/payment/mastercard.png'),
                         'amex' => asset('images/payment/amex.png'),
                         'googlepay' => asset('images/payment/googlepay.png'),
                         'applepay' => asset('images/payment/applepay.png'),
                         'klarna' => asset('images/payment/klarna.jpg'),
                         'vipps' => asset('images/payment/vipps.png'),
                         'swish' => asset('images/payment/swish.png'),
                     ];
                     $checkoutOptionsByAcquirer = [];
                     foreach ($checkoutPaymentOptions as $option) {
                         $acquirer = (string) ($option['acquirer'] ?? '');
                         if ($acquirer === '') {
                             continue;
                         }
                         $checkoutOptionsByAcquirer[$acquirer][] = $option;
                     }
                 @endphp

                 @foreach ($checkoutOptionsByAcquirer as $acquirer => $options)
                     @php
                         $acquirerLabel = $acquirerLabels[$acquirer] ?? ucfirst($acquirer);
                         $groupId = 'payment-acquirer-'.$acquirer;
                     @endphp
                     <label for="{{ $groupId }}" class="w-100 d-block">
                         <div class="custom-control custom-checkbox "
                             style="border: 2px solid rgba(42, 100, 149, 1);margin-bottom:10px;border-radius: 16px; padding: 21px 18px;">

                             <div class="d-flex " style="gap:19px">
                                 <input type="radio" class="btn-check" name="payment" id="{{ $groupId }}"
                                     value="{{ $acquirer }}">
                                 <div class="d-flex align-items-center flex-wrap" style="gap:10px">
                                     <p
                                         style="font-size: 16px;font-weight: 600;font-family: 'Inter', sans-serif;color:rgba(4, 52, 92, 1);margin:0;">
                                         {{ $acquirerLabel }}
                                     </p>
                                     <div class="d-flex align-items-center flex-wrap" style="gap:10px">
                                         @foreach ($options as $option)
                                             @php
                                                 $optionLabel = $option['label'] ?? ($option['key'] ?? '');
                                                 $optionIcon = $option['icon'] ?? ($option['key'] ?? '');
                                                 $iconUrl = $paymentOptionIcons[$optionIcon] ?? null;
                                             @endphp
                                             @if ($iconUrl)
                                                 <img height="20" src="{{ $iconUrl }}" alt="{{ $optionLabel }}"
                                                     title="{{ $optionLabel }}"
                                                     style="height:20px;width:auto;max-height:20px;object-fit:contain;"
                                                     onerror="this.style.display='none'">
                                             @else
                                                 <span
                                                     style="font-size: 13px;font-weight: 500;color:rgba(4, 52, 92, 1)">{{ $optionLabel }}</span>
                                             @endif
                                         @endforeach
                                     </div>
                                 </div>
                             </div>

                         </div>
                     </label>
                 @endforeach
             </div>

             <div class="d-grid">

                 @if (!auth()->check() && $shop->force_register == 'Yes')
                     <button type="button" class=" btn-block btn btn-inline mx-2 mb-2 buy_btn paymentBtn  d-none"
                         id="force_register"><i class="fas fa-check"></i><span>{!! strip_tags(__('words.cart_checkout_btn')) !!}</span></button>
                 @elseif($shop->shipping_force_register == 'Yes' && !auth()->check())
                     <button type="button" class=" btn btn-block btn-inline mx-2 mb-2 buy_btn paymentBtn d-none "
                         id="shipping_force_register"><i
                             class="fas fa-check"></i><span>{!! strip_tags(__('words.cart_checkout_btn')) !!}</span></button>
                 @else
                     <button type="submit" class=" btn btn-block btn-inline mx-2 mb-2 buy_btn paymentBtn d-none "><i
                             class="fas fa-check"></i><span>{!! strip_tags(__('words.cart_checkout_btn')) !!}</span></button>
                 @endif
             </div>



         </div>
     </div>
     @if (!auth()->check() && ($shop->shipping_force_register == 'Yes' || $shop->force_register == 'Yes'))
         <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
             aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
             <div class="modal-dialog modal-dialog-centered" role="document">
                 <div class="modal-content container py-3">
                     <ul class="nav nav-tabs" id="myTab" role="tablist">
                         <li class="nav-item" role="presentation">
                             <button onclick="disableRegister()" class="nav-link active" id="ex1-tab-1"
                                 data-toggle="tab" data-target="#login" type="button" role="tab"
                                 aria-controls="login" aria-selected="true">{{ __('words.login') }}</button>
                         </li>
                         <li class="nav-item" role="presentation">
                             <button onclick="disableLogin()" class="nav-link" id="ex1-tab-2" data-toggle="tab"
                                 data-target="#register" type="button" role="tab" aria-controls="register"
                                 aria-selected="true">{{ __('words.register') }}</button>
                         </li>

                     </ul>
                     <div class="tab-content" id="myTabContent">
                         <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="home-tab">
                             <div class="card-header mb-3">{{ __('words.reg_sec_title') }}</div>
                             <div class="row">

                                 <div class="form-group col-md-6">
                                     <label for="name">{{ __('words.checkout_form_first_name_label') }}</label>
                                     <input id="name" class="form-control @error('name') is-invalid @enderror"
                                         name="user[register][name]" disabled type="text" autocomplete="on"
                                         value="{{ old('name', request()->first_name) }}">
                                     @error('name')
                                         <span class="invalid-feedback" role="alert">
                                             <strong>{{ $message }}</strong>
                                         </span>
                                     @enderror
                                 </div>
                                 <div class="form-group col-md-6">
                                     <label for="last_name">{{ __('words.checkout_form_lastname') }}</label>
                                     <input id="last_name"
                                         class="form-control @error('last_name') is-invalid @enderror"
                                         name="user[register][last_name]" disabled type="text"
                                         value="{{ old('last_name', request()->last_name) }}">
                                     @error('last_name')
                                         <span class="invalid-feedback" role="alert">
                                             <strong>{{ $message }}</strong>
                                         </span>
                                     @enderror
                                 </div>

                                 <div class="form-group col-md-6">
                                     <label for="email">{{ __('words.checkout_form_email') }}</label>
                                     <input id="emailField" class="form-control @error('email') is-invalid @enderror"
                                         name="user[register][email]" disabled type="text"
                                         value="{{ old('email', request()->email) }}">
                                     @error('email')
                                         <span class="invalid-feedback" role="alert">
                                             <strong>{{ $message }}</strong>
                                         </span>
                                     @enderror
                                 </div>
                                 <div class="form-group col-md-6">
                                     <label for="phone">{{ __('words.invoice_tel') }}</label>
                                     <input id="phone" class="form-control @error('phone') is-invalid @enderror"
                                         name="user[register][phone]" disabled type="text"
                                         value="{{ old('phone') }}">
                                     @error('phone')
                                         <span class="invalid-feedback" role="alert">
                                             <strong>{{ $message }}</strong>
                                         </span>
                                     @enderror
                                 </div>
                                 <div class="col-md-12">
                                     <div class="form-group">
                                         <label for="address">{{ __('words.invoice_address') }}</label>
                                         <input value="" type="text"
                                             class="form-control @error('address') is-invalid @enderror"
                                             id="address" placeholder="{{ __('words.invoice_address') }}"
                                             name="user[register][meta][address]" disabled>
                                         @error('address')
                                             <span class="invalid-feedback" role="alert">
                                                 <strong>{{ $message }}</strong>
                                             </span>
                                         @enderror
                                     </div>
                                 </div>
                                 <div class="col-md-8">
                                     <div class="form-group">
                                         <label for="country">{{ __('words.invoice_country') }}</label>
                                         <select name="user[register][meta][country]" id="country"
                                             class="form-control" disabled>
                                             @foreach (App\Constants\Constants::COUNTRIES as $country)
                                                 <option>
                                                     {{ $country }}
                                                 </option>
                                             @endforeach
                                         </select>
                                         @error('country')
                                             <span class="invalid-feedback" role="alert">
                                                 <strong>{{ $message }}</strong>
                                             </span>
                                         @enderror
                                     </div>
                                 </div>
                                 <div class="col-md-4">
                                     <div class="form-group">
                                         <label for="state">{{ __('words.invoice_state') }}</label>
                                         <input value="{{ old('state') }}" type="text"
                                             class="form-control @error('state') is-invalid @enderror" id="state"
                                             placeholder="{{ __('words.invoice_state') }}"
                                             name="user[register][meta][state]" disabled>
                                         @error('state')
                                             <span class="invalid-feedback" role="alert">
                                                 <strong>{{ $message }}</strong>
                                             </span>
                                         @enderror
                                     </div>
                                 </div>
                                 <div class="col-md-6">
                                     <div class="form-group">
                                         <label for="city">{{ __('words.invoice_place') }}</label>
                                         <input value="" type="text"
                                             class="form-control @error('city') is-invalid @enderror" id="city"
                                             placeholder="Sted" name="user[register][meta][city]" disabled>
                                         @error('city')
                                             <span class="invalid-feedback" role="alert">
                                                 <strong>{{ $message }}</strong>
                                             </span>
                                         @enderror
                                     </div>
                                 </div>
                                 <div class="col-md-6">
                                     <div class="form-group">
                                         <label for="post_code">{{ __('words.invoice_postcode') }}</label>
                                         <input value="" type="number" step="1"
                                             class="form-control @error('post_code') is-invalid @enderror"
                                             id="post_code" placeholder="Postnummer"
                                             name="user[register][meta][post_code]" disabled>
                                         @error('post_code')
                                             <span class="invalid-feedback" role="alert">
                                                 <strong>{{ $message }}</strong>
                                             </span>
                                         @enderror
                                     </div>
                                 </div>
                                 <div class="form-group col-md-12">
                                     <label for="password">{{ __('words.password') }}</label>
                                     <input id="password"
                                         class="form-control @error('password') is-invalid @enderror"
                                         name="user[register][password]" disabled type="password" />
                                     @error('password')
                                         <span class="invalid-feedback" role="alert">
                                             <strong>{{ $message }}</strong>
                                         </span>
                                     @enderror
                                 </div>





                             </div>
                             <button class="btn btn-primary btn-block" type="submit">{{ __('words.next') }}</button>


                         </div>
                         <div class="tab-pane fade  show active" id="login" role="tabpanel"
                             aria-labelledby="profile-tab">
                             <div class="card-header">{{ __('words.login_sec_title') }}</div>
                             <div class="row mt-3">
                                 <div class="col-12">
                                     <div class="form-group">
                                         <input placeholder="{{ __('words.checkout_form_email') }}" id="email"
                                             type="text" class="form-control @error('email') is-invalid @enderror"
                                             name="user[login][email]" value="{{ old('email') }}"
                                             autocomplete="email" autofocus>
                                         @error('email')
                                             <span class="invalid-feedback" role="alert">
                                                 <strong>{{ $message }}</strong>
                                             </span>
                                         @enderror
                                     </div>

                                 </div>
                                 <div class="col-12">
                                     <div class="form-group">
                                         <input placeholder="{{ __('words.password') }}" id="password"
                                             type="password"
                                             class="form-control @error('password') is-invalid @enderror"
                                             name="user[login][password]" autocomplete="current-password">
                                         @error('password')
                                             <span class="invalid-feedback" role="alert">
                                                 <strong>{{ $message }}</strong>
                                             </span>
                                         @enderror
                                         <div class="text-right text-small mt-2">
                                             <a
                                                 href="{{ route('password.request') }}">{{ __('words.forgot_password_label') }}</a>
                                         </div>
                                     </div>
                                 </div>
                                 <div class="col-12">
                                     <div class="custom-control custom-checkbox text-small">
                                         <input type="checkbox" class="custom-control-input" id="sign-in-remember">
                                         <label class="custom-control-label"
                                             for="sign-in-remember">{{ __('words.login_remember_me_label') }}</label>
                                     </div>

                                     <button class="btn btn-primary btn-block"
                                         type="submit">{{ __('words.login_btn') }}</button>

                                 </div>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
         </div>
     @endif
 </form>
 <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
 <script>
     $(document).ready(function() {
         disableRegister();


         $('#shipping_force_register').click(function() {

             let shipping = false;
             let input;
             [...$("input[name='shipping']")].map(el => {
                 if (shipping != true) {
                     shipping = $(el).prop('checked')
                     input = $(el);
                 }
             })

             console.log(shipping);
             if (shipping) {
                 if ($(input).attr('id') != 'shipping0') {
                     $('#exampleModalCenter').modal('show');
                     $('.modal-backdrop').removeClass('modal-backdrop');
                     document.getElementById("cartbutton").classList.remove("side_cart_btn");
                 } else {
                     $('#checkout_form').submit()
                 }
             } else {
                 alert('please select shipping method');
             }

         });
         $('#force_register').click(function() {

             let shipping = false;
             let input;
             [...$("input[name='shipping']")].map(el => {
                 if (shipping != true) {
                     shipping = $(el).prop('checked')
                     input = $(el);
                 }
             })

             console.log(shipping);
             if (shipping) {
                 $('#exampleModalCenter').modal('show');
                 $('.modal-backdrop').removeClass('modal-backdrop');
                 document.getElementById("cartbutton").classList.remove("side_cart_btn");

             } else {
                 alert('please select shipping method');
             }

         });
     });
 </script>
 <script>
     const disableRegister = () => {

         [...document.querySelectorAll("input[name ^='user[login]']")].map(el => {
             el.disabled = false
         });
         [...document.querySelectorAll("input[name ^='user[register][meta]']")].map(el => {
             el.disabled = true
         });
         [...document.querySelectorAll("select[name ^='user[register][meta]']")].map(el => {
             el.disabled = true
         });
         [...document.querySelectorAll("input[name ^='user[register]']")].map(el => {
             el.disabled = true
         });
     }

     const disableLogin = () => {

         [...document.querySelectorAll("input[name ^='user[login]']")].map(el => {
             el.disabled = true
         });

         [...document.querySelectorAll("input[name ^='user[register][meta]']")].map(el => {
             el.disabled = false
         });
         [...document.querySelectorAll("select[name ^='user[register][meta]']")].map(el => {
             el.disabled = false
         });
         [...document.querySelectorAll("input[name ^='user[register]']")].map(el => {
             el.disabled = false
         });
     }

     const showPaymentOptions = (display = false) => {
         if (display == true) {
             document.getElementById('paymentOptions').style.display = 'block';
         } else {
             document.getElementById('paymentOptions').style.display = 'none';
         }
     }

     document.getElementById('terms').addEventListener('change', (event) => {
         showPaymentOptions(event.target.checked);
     })


     document.addEventListener('DOMContentLoaded', function() {
         showPaymentOptions(document.getElementById('terms').checked);
     })


     document.querySelectorAll("[name='payment']").forEach(element => {
         element.addEventListener('change', (el) => {
             if (el.target.checked) {
                 document.querySelector('.paymentBtn').click();
             }
         });
     });
 </script>
