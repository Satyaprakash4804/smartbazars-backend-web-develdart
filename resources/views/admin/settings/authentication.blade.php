@extends('layouts.admin.app', ['page' => $menuAdmin['settings']['active'] ?? "", 'sub_page' => $menuAdmin['settings']['route']['authentication']['sub_active'] ?? "" ])



@section('title', __('labels.authentication_settings'))



@section('header_data')

    @php

        $page_title = __('labels.authentication_settings');

        $page_pretitle = __('labels.admin') . " " . __('labels.settings');

    @endphp

@endsection



@php

    $breadcrumbs = [

        ['title' => __('labels.home'), 'url' => route('admin.dashboard')],

        ['title' => __('labels.settings'), 'url' => route('admin.settings.index')],

        ['title' => __('labels.authentication_settings'), 'url' => null],

    ];

@endphp



@section('admin-content')

    <div class="page-header d-print-none">

        <div class="row g-2 align-items-center">

            <div class="col">

                <h2 class="page-title">{{ __('labels.authentication_settings') }}</h2>

                <x-breadcrumb :items="$breadcrumbs"/>

            </div>

        </div>

    </div>

    <div class="page-body">

        <div class="container-xl">

            <div class="row g-5">

                <div class="col-sm-2 d-none d-lg-block">

                    <div class="sticky-top">

                        <h3>{{ __('labels.menu') }}</h3>

                        <nav class="nav nav-vertical nav-pills" id="pills">

                            <a class="nav-link" href="#pills-sms-gateway">{{ __('labels.sms_gateway') }}</a>

                            <a class="nav-link" href="#pills-custom-sms">{{ __('labels.custom_sms') }}</a>

                            <a class="nav-link" href="#pills-google-keys">{{ __('labels.google_keys') }}</a>

                            <a class="nav-link" href="#pills-firebase">{{ __('labels.firebase') }}</a>

                            <a class="nav-link" href="#pills-social-login">{{ __('labels.social_login') }}</a>

                        </nav>

                    </div>

                </div>

                <div class="col-sm" data-bs-spy="scroll" data-bs-target="#pills" data-bs-offset="0">

                    <div class="row row-cards">

                        <div class="col-12">

                            <form action="{{ route('admin.settings.store') }}" class="form-submit" method="post">

                                @csrf

                                <input type="hidden" name="type" value="authentication">

                                <div class="card mb-4" id="pills-sms-gateway">

                                    <div class="card-header">

                                        <h4 class="card-title">{{ __('labels.sms_gateway') }}</h4>

                                    </div>

                                    <div class="card-body">



                                        <div class="alert alert-info">

                                            <strong>Note:</strong> SMS Gateway priority logic:

                                            <ul class="mb-0 mt-2">

                                                <li>If Custom SMS is enabled → Gateway: <strong>Custom</strong> (Priority)</li>

                                                <li>If Custom SMS is disabled but Firebase is enabled → Gateway: <strong>Firebase</strong></li>

                                                <li>If both are disabled → Gateway: <strong>Not Set</strong></li>

                                            </ul>

                                            <small class="text-muted">Both Firebase and Custom SMS can be enabled simultaneously. Custom SMS takes priority when enabled.</small>

                                        </div>

                                    </div>

                                </div>

                                <div class="card mb-4" id="pills-custom-sms">

                                    <div class="card-header">

                                        <h4 class="card-title">{{ __('labels.custom_sms') }}</h4>

                                    </div>

                                    <div class="card-body">

                                        <div class="mb-3">

                                            <label class="row">

                                                <span class="col">{{ __('labels.enable_custom_sms') }}</span>

                                                <span class="col-auto">

                                                        <label class="form-check form-check-single form-switch">

                                                            <input class="form-check-input" type="checkbox"

                                                                   name="customSms" value="1" {{ isset($settings['customSms']) && $settings['customSms'] ? 'checked' : '' }} />

                                                        </label>

                                                    </span>

                                            </label>

                                        </div>

                                        <div id="customSmsFields"

                                             style="{{ isset($settings['customSms']) && $settings['customSms'] ? 'display: block;' : 'display: none;' }}">

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.custom_sms_url') }}</label>

                                                <input type="url" class="form-control" name="customSmsUrl"

                                                       placeholder="{{ __('labels.custom_sms_url_placeholder') }}"

                                                       value="{{ $settings['customSmsUrl'] ?? '' }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.custom_sms_method') }}</label>

                                                <select class="form-select" name="customSmsMethod">

                                                    <option

                                                        value="" {{ !isset($settings['customSmsMethod']) ? 'selected' : '' }}>{{ __('labels.custom_sms_method_placeholder') }}</option>

                                                    <option

                                                        value="GET" {{ isset($settings['customSmsMethod']) && $settings['customSmsMethod'] === 'GET' ? 'selected' : '' }}>

                                                        GET

                                                    </option>

                                                    <option

                                                        value="POST" {{ isset($settings['customSmsMethod']) && $settings['customSmsMethod'] === 'POST' ? 'selected' : '' }}>

                                                        POST

                                                    </option>

                                                </select>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.custom_sms_token_account_sid') }}</label>

                                                <input type="text" class="form-control"

                                                       name="customSmsTokenAccountSid"

                                                       placeholder="{{ __('labels.custom_sms_token_account_sid_placeholder') }}"

                                                       value="{{ $settings['customSmsTokenAccountSid'] ?? '' }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.custom_sms_auth_token') }}</label>

                                                <input type="text" class="form-control" name="customSmsAuthToken"

                                                       placeholder="{{ __('labels.custom_sms_auth_token_placeholder') }}"

                                                       value="{{ $settings['customSmsAuthToken'] ?? '' }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.custom_sms_text_format_data') }}</label>

                                                <input type="text" class="form-control"

                                                       name="customSmsTextFormatData"

                                                       placeholder="{{ __('labels.custom_sms_text_format_data_placeholder') }}"

                                                       value="{{ $settings['customSmsTextFormatData'] ?? '' }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.custom_sms_header') }}</label>

                                                <div id="headerFields">

                                                    @if(isset($settings['customSmsHeaderKey']) && is_array($settings['customSmsHeaderKey']))

                                                        @foreach($settings['customSmsHeaderKey'] as $index => $key)

                                                            <div class="row mb-2 header-field">

                                                                <div class="col-md-5">

                                                                    <input type="text" class="form-control"

                                                                           name="customSmsHeaderKey[]"

                                                                           placeholder="{{ __('labels.custom_sms_header_key_placeholder') }}"

                                                                           value="{{ $key }}"/>

                                                                </div>

                                                                <div class="col-md-5">

                                                                    <input type="text" class="form-control"

                                                                           name="customSmsHeaderValue[]"

                                                                           placeholder="{{ __('labels.custom_sms_header_value_placeholder') }}"

                                                                           value="{{ $settings['customSmsHeaderValue'][$index] ?? '' }}"/>

                                                                </div>

                                                                <div class="col-md-2">

                                                                    <button type="button"

                                                                            class="btn btn-danger btn-sm remove-field">

                                                                        <svg xmlns="http\://www\.w3.org/2000/svg"

                                                                             class="icon icon-tabler icon-tabler-trash"

                                                                             width="24" height="24"

                                                                             viewBox="0 0 24 24" stroke-width="2"

                                                                             stroke="currentColor" fill="none"

                                                                             stroke-linecap="round"

                                                                             stroke-linejoin="round">

                                                                            <path stroke="none" d="M0 0h24v24H0z"

                                                                                  fill="none"/>

                                                                            <path d="M4 7l16 0"/>

                                                                            <path d="M10 11l0 6"/>

                                                                            <path d="M14 11l0 6"/>

                                                                            <path

                                                                                d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/>

                                                                            <path

                                                                                d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>

                                                                        </svg>

                                                                    </button>

                                                                </div>

                                                            </div>

                                                        @endforeach

                                                    @endif

                                                </div>

                                                <button type="button" class="btn btn-primary mt-2"

                                                        id="addHeaderField">

                                                    <svg xmlns="http\://www\.w3.org/2000/svg"

                                                         class="icon icon-tabler icon-tabler-plus" width="24"

                                                         height="24" viewBox="0 0 24 24" stroke-width="2"

                                                         stroke="currentColor" fill="none" stroke-linecap="round"

                                                         stroke-linejoin="round">

                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>

                                                        <path d="M12 5l0 14"/>

                                                        <path d="M5 12l14 0"/>

                                                    </svg>

                                                    {{ __('labels.add_header') }}

                                                </button>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.custom_sms_params') }}</label>

                                                <div id="paramsFields">

                                                    @if(isset($settings['customSmsParamsKey']) && is_array($settings['customSmsParamsKey']))

                                                        @foreach($settings['customSmsParamsKey'] as $index => $key)

                                                            <div class="row mb-2 params-field">

                                                                <div class="col-md-5">

                                                                    <input type="text" class="form-control"

                                                                           name="customSmsParamsKey[]"

                                                                           placeholder="{{ __('labels.custom_sms_params_key_placeholder') }}"

                                                                           value="{{ $key }}"/>

                                                                </div>

                                                                <div class="col-md-5">

                                                                    <input type="text" class="form-control"

                                                                           name="customSmsParamsValue[]"

                                                                           placeholder="{{ __('labels.custom_sms_params_value_placeholder') }}"

                                                                           value="{{ $settings['customSmsParamsValue'][$index] ?? '' }}"/>

                                                                </div>

                                                                <div class="col-md-2">

                                                                    <button type="button"

                                                                            class="btn btn-danger btn-sm remove-field">

                                                                        <svg xmlns="http\://www\.w3.org/2000/svg"

                                                                             class="icon icon-tabler icon-tabler-trash"

                                                                             width="24" height="24"

                                                                             viewBox="0 0 24 24" stroke-width="2"

                                                                             stroke="currentColor" fill="none"

                                                                             stroke-linecap="round"

                                                                             stroke-linejoin="round">

                                                                            <path stroke="none" d="M0 0h24v24H0z"

                                                                                  fill="none"/>

                                                                            <path d="M4 7l16 0"/>

                                                                            <path d="M10 11l0 6"/>

                                                                            <path d="M14 11l0 6"/>

                                                                            <path

                                                                                d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/>

                                                                            <path

                                                                                d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>

                                                                        </svg>

                                                                    </button>

                                                                </div>

                                                            </div>

                                                        @endforeach

                                                    @endif

                                                </div>

                                                <button type="button" class="btn btn-primary mt-2"

                                                        id="addParamsField">

                                                    <svg xmlns="http\://www\.w3.org/2000/svg"

                                                         class="icon icon-tabler icon-tabler-plus" width="24"

                                                         height="24" viewBox="0 0 24 24" stroke-width="2"

                                                         stroke="currentColor" fill="none" stroke-linecap="round"

                                                         stroke-linejoin="round">

                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>

                                                        <path d="M12 5l0 14"/>

                                                        <path d="M5 12l14 0"/>

                                                    </svg>

                                                    {{ __('labels.add_param') }}

                                                </button>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.custom_sms_body') }}</label>

                                                <div id="bodyFields">

                                                    @if(isset($settings['customSmsBodyKey']) && is_array($settings['customSmsBodyKey']))

                                                        @foreach($settings['customSmsBodyKey'] as $index => $key)

                                                            <div class="row mb-2 body-field">

                                                                <div class="col-md-5">

                                                                    <input type="text" class="form-control"

                                                                           name="customSmsBodyKey[]"

                                                                           placeholder="{{ __('labels.custom_sms_body_key_placeholder') }}"

                                                                           value="{{ $key }}"/>

                                                                </div>

                                                                <div class="col-md-5">

                                                                    <input type="text" class="form-control"

                                                                           name="customSmsBodyValue[]"

                                                                           placeholder="{{ __('labels.custom_sms_body_value_placeholder') }}"

                                                                           value="{{ $settings['customSmsBodyValue'][$index] ?? '' }}"/>

                                                                </div>

                                                                <div class="col-md-2">

                                                                    <button type="button"

                                                                            class="btn btn-danger btn-sm remove-field">

                                                                        <svg xmlns="http\://www\.w3.org/2000/svg"

                                                                             class="icon icon-tabler icon-tabler-trash"

                                                                             width="24" height="24"

                                                                             viewBox="0 0 24 24" stroke-width="2"

                                                                             stroke="currentColor" fill="none"

                                                                             stroke-linecap="round"

                                                                             stroke-linejoin="round">

                                                                            <path stroke="none" d="M0 0h24v24H0z"

                                                                                  fill="none"/>

                                                                            <path d="M4 7l16 0"/>

                                                                            <path d="M10 11l0 6"/>

                                                                            <path d="M14 11l0 6"/>

                                                                            <path

                                                                                d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12"/>

                                                                            <path

                                                                                d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3"/>

                                                                        </svg>

                                                                    </button>

                                                                </div>

                                                            </div>

                                                        @endforeach

                                                    @endif

                                                </div>

                                                <button type="button" class="btn btn-primary mt-2"

                                                        id="addBodyField">

                                                    <svg xmlns="http\://www\.w3.org/2000/svg"

                                                         class="icon icon-tabler icon-tabler-plus" width="24"

                                                         height="24" viewBox="0 0 24 24" stroke-width="2"

                                                         stroke="currentColor" fill="none" stroke-linecap="round"

                                                         stroke-linejoin="round">

                                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>

                                                        <path d="M12 5l0 14"/>

                                                        <path d="M5 12l14 0"/>

                                                    </svg>

                                                    {{ __('labels.add_body') }}

                                                </button>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <div class="card mb-4" id="pills-google-keys">

                                    <div class="card-header">

                                        <h4 class="card-title">{{ __('labels.google_recaptcha') }}</h4>

                                    </div>

                                    <div class="card-body">

                                        <div class="mb-3">

                                            <label

                                                class="form-label">{{ __('labels.google_recaptcha_site_key') }}</label>

                                            <input type="text" class="form-control" name="googleRecaptchaSiteKey"

                                                   placeholder="{{ __('labels.google_recaptcha_site_key_placeholder') }}"

                                                   value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['googleRecaptchaSiteKey'] ?? '***'), '***', 3, 8) : ($settings['googleRecaptchaSiteKey'] ?? '') }}"/>

                                        </div>

                                        <div class="mb-3">

                                            <label class="form-label">{{ __('labels.google_api_key') }}</label>

                                            <input type="text" class="form-control" name="googleApiKey"

                                                   placeholder="{{ __('labels.enter_google_api_key') }}"

                                                   value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['googleApiKey'] ?? '***'), '***', 3, 8) : ($settings['googleApiKey'] ?? '') }}"/>

                                        </div>

                                    </div>

                                </div>

                                <div class="card mb-4" id="pills-firebase">

                                    <div class="card-header">

                                        <h4 class="card-title">{{ __('labels.firebase') }}</h4>

                                    </div>

                                    <div class="card-body">

                                        <div class="mb-3">

                                            <label class="row">

                                                <span class="col">{{ __('labels.enable_firebase') }}</span>

                                                <span class="col-auto">

                                                        <label class="form-check form-check-single form-switch">

                                                            <input class="form-check-input" type="checkbox"

                                                                   id="firebaseToggle" name="firebase" value="1" {{ isset($settings['firebase']) && $settings['firebase'] ? 'checked' : '' }} />

                                                        </label>

                                                    </span>

                                            </label>

                                        </div>

                                        <div id="firebaseFields"

                                             style="{{ old('firebase', $settings['firebase'] ?? false) ? 'display: block;' : 'display: none;' }}">

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.firebase_api_key') }}</label>

                                                <input type="text" class="form-control" name="fireBaseApiKey"

                                                       placeholder="{{ __('labels.firebase_api_key_placeholder') }}"

                                                       value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['fireBaseApiKey'] ?? '***'), '***', 3, 8) : ($settings['fireBaseApiKey'] ?? '') }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.firebase_auth_domain') }}</label>

                                                <input type="text" class="form-control" name="fireBaseAuthDomain"

                                                       placeholder="{{ __('labels.firebase_auth_domain_placeholder') }}"

                                                       value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['fireBaseAuthDomain'] ?? '***'), '***', 3, 8) : ($settings['fireBaseAuthDomain'] ?? '') }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.firebase_database_url') }}</label>

                                                <input type="url" class="form-control" name="fireBaseDatabaseURL"

                                                       placeholder="{{ __('labels.firebase_database_url_placeholder') }}"

                                                       value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['fireBaseDatabaseURL'] ?? '***'), '***', 3, 8) : ($settings['fireBaseDatabaseURL'] ?? '') }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.firebase_project_id') }}</label>

                                                <input type="text" class="form-control" name="fireBaseProjectId"

                                                       placeholder="{{ __('labels.firebase_project_id_placeholder') }}"

                                                       value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['fireBaseProjectId'] ?? '***'), '***', 3, 8) : ($settings['fireBaseProjectId'] ?? '') }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.firebase_storage_bucket') }}</label>

                                                <input type="text" class="form-control" name="fireBaseStorageBucket"

                                                       placeholder="{{ __('labels.firebase_storage_bucket_placeholder') }}"

                                                       value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['fireBaseStorageBucket'] ?? '***'), '***', 3, 8) : ($settings['fireBaseStorageBucket'] ?? '') }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.firebase_messaging_sender_id') }}</label>

                                                <input type="text" class="form-control"

                                                       name="fireBaseMessagingSenderId"

                                                       placeholder="{{ __('labels.firebase_messaging_sender_id_placeholder') }}"

                                                       value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['fireBaseMessagingSenderId'] ?? '***'), '***', 3, 8) : ($settings['fireBaseMessagingSenderId'] ?? '') }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.firebase_app_id') }}</label>

                                                <input type="text" class="form-control" name="fireBaseAppId"

                                                       placeholder="{{ __('labels.firebase_app_id_placeholder') }}"

                                                       value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['fireBaseAppId'] ?? '***'), '***', 3, 8) : ($settings['fireBaseAppId'] ?? '') }}"/>

                                            </div>

                                            <div class="mb-3">

                                                <label

                                                    class="form-label required">{{ __('labels.firebase_measurement_id') }}</label>

                                                <input type="text" class="form-control" name="fireBaseMeasurementId"

                                                       placeholder="{{ __('labels.firebase_measurement_id_placeholder') }}"

                                                       value="{{ ($systemSettings['demoMode'] ?? false) ? Str::mask(($settings['fireBaseMeasurementId'] ?? '***'), '***', 3, 8) : ($settings['fireBaseMeasurementId'] ?? '') }}"/>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <div class="card mb-4" id="pills-social-login">

                                    <div class="card-header">

                                        <h4 class="card-title">{{ __('labels.social_login') }}</h4>

                                    </div>

                                    <div class="card-body">

                                        <div class="mb-3">

                                            <label class="row">

                                                <span class="col">{{ __('labels.apple_login') }}</span>

                                                <span class="col-auto">

                                                        <label class="form-check form-check-single form-switch">

                                                            <input class="form-check-input" type="checkbox"

                                                                   name="appleLogin" value="1" {{ isset($settings['appleLogin']) && $settings['appleLogin'] ? 'checked' : '' }} />

                                                        </label>

                                                    </span>

                                            </label>

                                        </div>

                                        <div class="mb-3">

                                            <label class="row">

                                                <span class="col">{{ __('labels.google_login') }}</span>

                                                <span class="col-auto">

                                                        <label class="form-check form-check-single form-switch">

                                                            <input class="form-check-input" type="checkbox"

                                                                   name="googleLogin" value="1" {{ isset($settings['googleLogin']) && $settings['googleLogin'] ? 'checked' : '' }} />

                                                        </label>

                                                    </span>

                                            </label>

                                        </div>

{{--                                        <div class="mb-3">--}}

{{--                                            <label class="row">--}}

{{--                                                <span class="col">{{ __('labels.facebook_login') }}</span>--}}

{{--                                                <span class="col-auto">--}}

{{--                                                        <label class="form-check form-check-single form-switch">--}}

{{--                                                            <input class="form-check-input" type="checkbox"--}}

{{--                                                                   name="facebookLogin" value="1" {{ isset($settings['facebookLogin']) && $settings['facebookLogin'] ? 'checked' : '' }} />--}}

{{--                                                        </label>--}}

{{--                                                    </span>--}}

{{--                                            </label>--}}

{{--                                        </div>--}}

                                    </div>

                                </div>

                                <div class="card-footer text-end">

                                    <div class="d-flex">

                                        @can('updateSetting', [\App\Models\Setting::class, 'authentication'])

                                            <button type="submit"

                                                    class="btn btn-primary ms-auto">{{ __('labels.submit') }}</button>

                                        @endcan

                                    </div>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script>

        function addField(containerId, keyName, valueName, keyPlaceholder, valuePlaceholder) {

            const container = document.getElementById(containerId);

            const fieldDiv = document.createElement('div');

            fieldDiv.className = 'row mb-2 ' + containerId.replace('Fields', '') + '-field';

            fieldDiv.innerHTML = `

                <div class="col-md-5">

                    <input type="text" class="form-control" name="${keyName}[]" placeholder="${keyPlaceholder}" />

                </div>

                <div class="col-md-5">

                    <input type="text" class="form-control" name="${valueName}[]" placeholder="${valuePlaceholder}" />

                </div>

                <div class="col-md-2">

                    <button type="button" class="btn btn-danger btn-sm remove-field">

                        <svg xmlns="http\://www\.w3.org/2000/svg" class="icon icon-tabler icon-tabler-trash" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">

                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>

                            <path d="M4 7l16 0" />

                            <path d="M10 11l0 6" />

                            <path d="M14 11l0 6" />

                            <path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" />

                            <path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" />

                        </svg>

                    </button>

                </div>

            `;

            container.appendChild(fieldDiv);

        }



        const addHeaderFieldButton = document.getElementById('addHeaderField');
        if (addHeaderFieldButton) addHeaderFieldButton.addEventListener('click', () => {

            addField('headerFields', 'customSmsHeaderKey', 'customSmsHeaderValue', '{{ __('labels.custom_sms_header_key_placeholder') }}', '{{ __('labels.custom_sms_header_value_placeholder') }}');

        });



        const addParamsFieldButton = document.getElementById('addParamsField');
        if (addParamsFieldButton) addParamsFieldButton.addEventListener('click', () => {

            addField('paramsFields', 'customSmsParamsKey', 'customSmsParamsValue', '{{ __('labels.custom_sms_params_key_placeholder') }}', '{{ __('labels.custom_sms_params_value_placeholder') }}');

        });



        const addBodyFieldButton = document.getElementById('addBodyField');
        if (addBodyFieldButton) addBodyFieldButton.addEventListener('click', () => {

            addField('bodyFields', 'customSmsBodyKey', 'customSmsBodyValue', '{{ __('labels.custom_sms_body_key_placeholder') }}', '{{ __('labels.custom_sms_body_value_placeholder') }}');

        });



        document.addEventListener('click', (e) => {

            const target = e.target instanceof Element ? e.target : null;
            const removeButton = target && (target.matches('.remove-field') ? target : target.closest('.remove-field'));
            if (removeButton) {
                const row = removeButton.closest('.row');
                if (row) row.remove();
            }

        });



        const customSmsToggle = document.querySelector('input[name="customSms"]');

        const customSmsFields = document.getElementById('customSmsFields');

        const firebaseToggle = document.querySelector('input[name="firebase"]');

        const firebaseFields = document.getElementById('firebaseFields');

        const googleLoginToggle = document.querySelector('input[name="googleLogin"]');



        const toggleCustomSmsFields = () => {
            if (customSmsFields && customSmsToggle) {
                customSmsFields.style.display = customSmsToggle.checked ? 'block' : 'none';
            }
        };

        const toggleFirebaseFields = () => {
            if (firebaseFields && firebaseToggle) {
                firebaseFields.style.display = firebaseToggle.checked ? 'block' : 'none';
            }
        };



        // Validation function to show SMS gateway priority

        const validateMutuallyExclusiveSettings = () => {

            const customSmsEnabled = !!(customSmsToggle && customSmsToggle.checked);

            const firebaseEnabled = !!(firebaseToggle && firebaseToggle.checked);

            const googleLoginEnabled = !!(googleLoginToggle && googleLoginToggle.checked);



            // Remove any existing warnings

            const warning = document.getElementById('validation-warning');

            if (warning) {

                warning.remove();

            }



            // Enable submit button - no restrictions

            const submitBtn = document.querySelector('button[type="submit"]');

            if (submitBtn) {

                submitBtn.disabled = false;

                submitBtn.title = '';

            }



            return true;

        };



        // Add change event listeners for validation

        if (googleLoginToggle) googleLoginToggle.addEventListener('change', validateMutuallyExclusiveSettings);



        // Form submission validation

        const form = document.querySelector('.form-submit');

        if (form) form.addEventListener('submit', (e) => {

            if (!validateMutuallyExclusiveSettings()) {

                e.preventDefault();

                const warning = document.getElementById('validation-warning');

                if (warning) {

                    warning.scrollIntoView({ behavior: 'smooth', block: 'center' });

                }

                return false;

            }

        });



        // Initialize

        toggleCustomSmsFields();

        toggleFirebaseFields();

        validateMutuallyExclusiveSettings();

        updateSmsGatewayDisplay();



        // Function to update SMS gateway display with priority logic

        function updateSmsGatewayDisplay() {

            const customSmsEnabled = !!(customSmsToggle && customSmsToggle.checked);

            const firebaseEnabled = !!(firebaseToggle && firebaseToggle.checked);

            const gatewayElement = document.getElementById('activeSmsGateway');



            let gateway = '';

            let badgeClass = 'bg-secondary';



            // Custom SMS has priority over Firebase

            if (customSmsEnabled) {

                gateway = 'Custom (Priority)';

                badgeClass = 'bg-primary';

            } else if (firebaseEnabled) {

                gateway = 'Firebase';

                badgeClass = 'bg-success';

            } else {

                gateway = 'Not Set';

                badgeClass = 'bg-secondary';

            }



            if (gatewayElement) {
                gatewayElement.textContent = gateway;
                gatewayElement.className = `badge ${badgeClass}`;
            }

        }



        // Update SMS gateway display when toggles change

        if (customSmsToggle) customSmsToggle.addEventListener('change', () => {
            toggleCustomSmsFields();
            validateMutuallyExclusiveSettings();
            updateSmsGatewayDisplay();
        });



        if (firebaseToggle) firebaseToggle.addEventListener('change', () => {
            toggleFirebaseFields();
            validateMutuallyExclusiveSettings();
            updateSmsGatewayDisplay();
        });



        if (googleLoginToggle) googleLoginToggle.addEventListener('change', validateMutuallyExclusiveSettings);

    </script>

@endsection
