<?php
$MESS['CLEANTALK_LABEL_STATUS'] = 'Модуль включен';
$MESS['CLEANTALK_LABEL_KEY']    = 'Ключ доступа';
$MESS['CLEANTALK_LABEL_NEW_USER']       = 'Защита формы регистрации';
$MESS['CLEANTALK_LABEL_COMMENT_BLOG']   = 'Защита формы комментариев блога';
$MESS['CLEANTALK_LABEL_COMMENT_FORUM']  = 'Защита формы комментариев форума';
$MESS['CLEANTALK_LABEL_FORUM_PRIVATE_MESSAGES']  = 'Защита личных сообщений форума';
$MESS['CLEANTALK_LABEL_COMMENT_TREELIKE']  = 'Защита форм Древовидных комментариев';
$MESS['CLEANTALK_LABEL_SEND_EXAMPLE']  = 'Отсылать тексты для офф-топ анализа';
$MESS['CLEANTALK_LABEL_ORDER']  = 'Проверять формы заказов';
$MESS['CLEANTALK_LABEL_WEB_FORMS'] = 'Защита Веб-форм';
$MESS['CLEANTALK_BUTTON_SAVE']  = 'Сохранить';
$MESS['CLEANTALK_GET_AUTO_KEY']  = 'Получить ключ автоматически';
$MESS['CLEANTALK_GET_MANUAL_KEY']  = 'Получить ключ вручную';
$MESS['CLEANTALK_GET_TO_CP']  = 'Перейти в панель управления CleanTalk';
$MESS['CLEANTALK_BUTTON_RESET'] = 'Сбросить';
$MESS['CLEANTALK_TITLE']        = 'Настройки модуля';
$MESS['CLEANTALK_LABEL_GLOBAL_CHECK']        = 'Защита любых форм';
$MESS['CLEANTALK_LABEL_GLOBAL_CHECK_WITHOUT_EMAIL'] = 'Проверять все POST данные';
$MESS['CLEANTALK_WARNING_GLOBAL_CHECK_WITHOUT_EMAIL'] = '- Внимание, возможны конфликты!';
$MESS['CLEANTALK_LABEL_BOT_DETECTOR'] = 'Использовать Anti-Spam by CleanTalk JavaScript библиотеку';
$MESS['CLEANTALK_DESCRIPTION_BOT_DETECTOR'] = 'Эта опция включает внешнюю Anti-Spam by CleanTalk JavaScript библиотеку для получения данных о посетителях';
$MESS['CLEANTALK_LABEL_SFW']        = 'Spam FireWall';
$MESS['CLEANTALK_LABEL_UNIQ_GET_OPTION']        = 'Добавление уникального гет параметра';
$MESS['CLEANTALK_LABEL_UNIQ_GET_OPTION_DESC']        = 'Если посетитель попадает на страницу файервола, плагин помещает уникальный GET параметр в URL-адрес, чтобы избежать проблем с кэшированием.';
$MESS['CLEANTALK_LABEL_NOTIFY']        = "Нравится антиспам от CleanTalk? Помогите другим узнать о нас! <a target='_blank' href='http://marketplace.1c-bitrix.ru/solutions/cleantalk.antispam/#rating'>Оставьте отзыв на Битрикс.Маркетплейс</a>";
$MESS['CLEANTALK_ENTER_KEY']        = 'Введите ключ';
$MESS['CLEANTALK_KEY_VALID']        = 'Ключ активен';
$MESS['CLEANTALK_KEY_NOT_VALID']    = 'Ключ не активен';
$MESS['CLEANTALK_EMAIL_REGISTRATION_WARNING']	= 'Для регистрации будет использована почта администратора';
$MESS['CLEANTALK_API_KEY_GETTING_WARNING']	= sprintf(
    'Пожалуйста, используйте ключ доступа из вашей %s панели управления CleanTalk %s',
    '<a href="https://cleantalk.org/my/?cp_mode=antispam" target="_blank">',
    '</a>'
);
$MESS['CLEANTALK_LICENSE_AGREEMENT'] = 'Лицензионное соглашение';
$MESS['CLEANTALK_KEY']                           = 'Ключ доступа';
$MESS['CLEANTALK_EXCLUSIONS']                    = 'Исключения';
$MESS['CLEANTALK_EXCLUSIONS_URL']                = 'URL исключения';
$MESS['CLEANTALK_EXCLUSIONS_URL_DESCRIPTION']    = 'Исключение URL из спам-проверки. Перечислите через запятую.';
$MESS['CLEANTALK_EXCLUSIONS_URLS_REGEXP_DESCRIPTION'] = 'Использовать регулярные выражения в исключении по URL';
$MESS['CLEANTALK_EXCLUSIONS_FIELDS']             = 'Исключение полей форм';
$MESS['CLEANTALK_EXCLUSIONS_FIELDS_DESCRIPTION'] = 'Исключение полей форм из спам-проверки. Перечислите через запятую. Это работает на формах, кроме форм регистрации и комментирования.';
$MESS['CLEANTALK_EXCLUSIONS_FIELDS_REGEXP_DESCRIPTION'] = 'Использовать регулярные выражения в исключении полей';
$MESS['CLEANTALK_EXCLUSIONS_WEBFORM']            = 'Исключение Веб-форм по ID';
$MESS['CLEANTALK_EXCLUSIONS_WEBFORM_DESCRIPTION']= 'Исключение форм (модуль Веб-формы) из спам-проверки по ID. Перечислите через запятую.';
$MESS['CLEANTALK_EXCLUSIONS_SITES'] = 'Исключение сайтов';
$MESS['CLEANTALK_EXCLUSIONS_SITES_DESCRIPTION'] = 'Исключение сайтов из спам-проверки. Удерживайте CTRL, чтобы выбрать несколько сайтов.';
$MESS['CLEANTALK_TRIAL_NOTIFY']= "Заканчивается ознакомительный срок пользования плагина <b>Антиспам без CAPTCHA от CleanTalk</b>. Пожалуйста, подключите тариф в <a href='https://cleantalk.org/my/bill/recharge?utm_source=bitrix-backend&utm_medium=cpc&utm_campaign=bitrix-backend-trial&user_token=".COption::GetOptionString('cleantalk.antispam', 'user_token', '')."&cp_mode=antispam' target='_blank'><b>панели управления</b></a>.";
$MESS['CLEANTALK_RENEW_NOTIFY']= "Пожалуйста, <a href='https://cleantalk.org/my/bill/recharge?utm_source=bitrix-backend&utm_medium=cpc&utm_campaign=bitrix-backend-renew&user_token=".COption::GetOptionString('cleantalk.antispam', 'user_token', '')."&cp_mode=antispam' target='_blank'><b>обновите</b></a> вашу анти-спам лицензию для <b>Антиспам без CAPTCHA от CleanTalk</b>!";
$MESS['CLEANTALK_MISC'] = 'Прочее';
$MESS['CLEANTALK_LABEL_COMPLETE_DEACTIVATION'] = 'Полная деактивация';
$MESS['CLEANTALK_WRONG_REGEXP_NOTIFY'] = 'Исключение полей форм является не корректным регулярным выражением.';
$MESS['CLEANTALK_WRONG_DEFAULT_SETTINGS'] = 'Невоможно загрузить опции по умолчанию. Ошибка в имени модуля.';
$MESS['CLEANTALK_WRONG_CURRENT_SETTINGS'] = 'Невоможно загрузить текущие опции.';
$MESS['CLEANTALK_RESET_OPTIONS_FAILED'] = 'Невоможно сбросить опции.';
$MESS['CLEANTALK_MULTISITE_LABEL_KEY'] = 'Если вы хотите использовать отдельный ключ доступа для этого сайта, вставьте его здесь. В противном случае оставьте поле пустым.';