<?php
require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

if (!$USER->IsAdmin()) {
    LocalRedirect('/');
}

\Bitrix\Main\Loader::includeModule('iblock');
$IBLOCK_ID = 5; 
$el = new CIBlockElement;
$arProps = [];

$rsProp = CIBlockPropertyEnum::GetList(
    ["SORT" => "ASC", "VALUE" => "ASC"],
    ['IBLOCK_ID' => $IBLOCK_ID]
);
while ($arProp = $rsProp->Fetch()) {
    $key = trim($arProp['VALUE']);
    $arProps[$arProp['PROPERTY_CODE']][$key] = $arProp['ID'];
}

$row = 1;
$added = 0;
$updated = 0;
$errors = 0;

$csvFile = $_SERVER['DOCUMENT_ROOT'] . '/vacancy.csv';

if (!file_exists($csvFile)) {
    echo "Файл vacancy.csv не найден!<br>";
    exit;
}

if (($handle = fopen($csvFile, "r")) !== false) {
    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        if ($row == 1) {
            $row++;
            continue; 
        }
        $row++;
        if (empty($data[3])) {
            continue;
        }

        $PROP = [];
        $PROP['ACTIVITY'] = $data[9] ?? '';       
        $PROP['FIELD'] = $data[11] ?? '';         
        $PROP['OFFICE'] = $data[1] ?? '';         
        $PROP['LOCATION'] = $data[2] ?? '';       
        $PROP['REQUIRE'] = $data[4] ?? '';        
        $PROP['DUTY'] = $data[5] ?? '';           
        $PROP['CONDITIONS'] = $data[6] ?? '';     
        $PROP['EMAIL'] = $data[12] ?? '';         
        $PROP['DATE'] = date('d.m.Y H:i:s'); 
        $PROP['TYPE'] = $data[8] ?? '';           
        $PROP['SALARY_TYPE'] = '';                
        $PROP['SALARY_VALUE'] = $data[7] ?? '';   
        $PROP['SCHEDULE'] = $data[10] ?? '';      

        foreach ($PROP as $key => &$value) {
            $value = trim($value);
            if (stripos($value, '•') !== false) {
                $items = explode('•', $value);
                $items = array_filter(array_map('trim', $items));
                $value = array_values($items); 
            }

            if (isset($arProps[$key]) && !is_array($value) && !empty($value)) {
                $found = false;

                if (isset($arProps[$key][$value])) {
                    $value = $arProps[$key][$value];
                    $found = true;
                }

                if (!$found) {
                    foreach ($arProps[$key] as $propValue => $propId) {
                        if (stripos($value, $propValue) !== false || 
                            stripos($propValue, $value) !== false) {
                            $value = $propId;
                            $found = true;
                            break;
                        }
                    }
                }
            }
        }

        if ($PROP['SALARY_VALUE'] == '-' || empty($PROP['SALARY_VALUE'])) {
            $PROP['SALARY_VALUE'] = '';
        } elseif (stripos($PROP['SALARY_VALUE'], 'договор') !== false) {
            $PROP['SALARY_VALUE'] = '';
            $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['Договорная'] ?? '';
        } else {
            $salaryText = preg_replace('/руб\.?|р\.?|рублей/ui', '', $PROP['SALARY_VALUE']);
            $salaryText = trim($salaryText);

            if (preg_match('/^от\s+/ui', $salaryText)) {
                $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['ОТ'] ?? '';
                $PROP['SALARY_VALUE'] = preg_replace('/^от\s+/ui', '', $salaryText);
            } elseif (preg_match('/^до\s+/ui', $salaryText)) {
                $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['ДО'] ?? '';
                $PROP['SALARY_VALUE'] = preg_replace('/^до\s+/ui', '', $salaryText);
            } else {
                $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['='] ?? '';
            }

            $PROP['SALARY_VALUE'] = preg_replace('/[^\d]/', '', $PROP['SALARY_VALUE']);
        }

        $existingId = false;
        $res = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $IBLOCK_ID,
                'NAME' => $data[3]
            ],
            false,
            false,
            ['ID']
        );

        if ($ob = $res->Fetch()) {
            $existingId = $ob['ID'];
        }

        $arLoadProductArray = [
            "MODIFIED_BY" => $USER->GetID(),
            "IBLOCK_SECTION_ID" => false,
            "IBLOCK_ID" => $IBLOCK_ID,
            "PROPERTY_VALUES" => $PROP,
            "NAME" => trim($data[3]), 
            "ACTIVE" => 'Y', 
            "DATE_ACTIVE_FROM" => $PROP['DATE'], 
        ];

        if ($existingId) {
            if ($el->Update($existingId, $arLoadProductArray)) {
                echo "Обновлен элемент с ID: " . $existingId . " - " . $data[3] . "<br>";
                $updated++;
            } else {
                echo "Ошибка обновления: " . $el->LAST_ERROR . " (ID: $existingId)<br>";
                $errors++;
            }
        } else {
            if ($PRODUCT_ID = $el->Add($arLoadProductArray)) {
                echo "Добавлен элемент с ID: " . $PRODUCT_ID . " - " . $data[3] . "<br>";
                $added++;
            } else {
                echo "Ошибка добавления: " . $el->LAST_ERROR . " (" . $data[3] . ")<br>";
                $errors++;
            }
        }
    }
    fclose($handle);

   } else {
    echo "Не удалось открыть файл vacancy.csv";
}