<?php

function compute_hash($data) {
    return $data ? sha1($data) : null;
}

function update_readme($categories) {
    $readme = fopen("README.md", "w");
    fwrite($readme,
        "# Screen Templates\nEnhance the usability and functionality of ProcessMaker. These templates offer offer features for selecting, saving, and designating default templates, ultimately streamlining the workflow."
    );
    ksort($categories);  // Sort categories alphabetically
    foreach ($categories as $category => $templates) {
        $category = str_replace("-", " ", $category);
        $category = ucwords($category);
        fwrite($readme, "\n## $category\n");
        // Sort templates alphabetically within each category
        usort($templates, function($a, $b) { return strcmp($a['name'], $b['name']); });
        
        foreach ($templates as $template) {
            foreach ($template as $value) {
                $string = "- **[{$value['name']}](/{$value['relative_path']})**: {$value['description']}";
                if ($value['version']) {
                    $string .= " (Version {$value['version']})\n";
                } else {
                    $string .= "\n";
                }
                fwrite($readme, $string);
            }

        }
    }
    fclose($readme);
}

function main()
{
    $rootDirectory = ".";
    $categories = [];

    // Iterate over category directories
    foreach (new DirectoryIterator($rootDirectory) as $categoryInfo) {
        if ($categoryInfo->isDot() || strpos($categoryInfo->getBasename(), ".") === 0) {
            continue;
        }

        if ($categoryInfo->isDir()) {
            $currentCategory = $categoryInfo->getFilename();
            
            if (!isset($categories[$currentCategory])) {
                $categories[$currentCategory] = [];
            }

            // Iterate over template directories within each category
            foreach (new DirectoryIterator($categoryInfo->getPathname()) as $templateInfo) {
                if ($templateInfo->isDot() || strpos($templateInfo->getBasename(), ".") === 0) {
                    continue;
                }

                if ($templateInfo->isDir()) {
                    $templateName = $templateInfo->getFilename();
                    $categories[$currentCategory][$templateName] = initializeTemplateStructure();

                    // Iterate over template contents
                    foreach (new DirectoryIterator($templateInfo->getPathname()) as $contentInfo) {
                        if ($contentInfo->isDot() || strpos($templateInfo->getBasename(), ".") === 0) {
                            continue;
                        }

                        handleTemplateContent($contentInfo, $categories, $currentCategory, $templateName);
                    }
                }
            }
        }
    }

    file_put_contents("index.json", json_encode($categories, JSON_PRETTY_PRINT));
}

function initializeTemplateStructure()
{
    return [
        "screen" => "",
        "template_details" => [
            "name" => "",
            "description" => "",
            "screen_type" => "",
            'version' => "",
        ],
        "assets" => [
            "thumbnail" => "",
            "preview-thumbs"  => []
        ],
    ];
}

function handleTemplateContent($contentInfo, &$categories, $currentCategory, $templateName)
{
    if ($contentInfo->isDir()) {
        handleAssetDirectory($contentInfo, $categories, $currentCategory, $templateName);
    } else {
        mapContentToTemplateStructure($contentInfo, $categories, $currentCategory, $templateName);
    }
}

function handleAssetDirectory($assetDirectory, &$categories, $currentCategory, $templateName)
{
    $assets = new DirectoryIterator($assetDirectory->getPathname());
    
    foreach ($assets as $assetFileInfo) {
        if ($assetFileInfo->isDot() || strpos($assetFileInfo->getBasename(), '.') === 0) {
            continue;
        }

        handleAssetFile($assetFileInfo, $categories, $currentCategory, $templateName);
    }
}

function handleAssetFile($assetFileInfo, &$categories, $currentCategory, $templateName)
{
    $assetName = $assetFileInfo->getFilename();
    $assetName = substr($assetName, 0, strrpos($assetName, "."));

    if ($assetName === 'thumbnail') {
        $categories[$currentCategory][$templateName]['assets']['thumbnail'] = $assetFileInfo->getPathname();
    }

    if ($assetFileInfo->isDir()) {
        handleSubDirectoryAssets($assetFileInfo, $categories, $currentCategory, $templateName);
    }
}

function handleSubDirectoryAssets($directory, &$categories, $currentCategory, $templateName)
{
    $path = explode('/', $directory->getPathname());
    $directoryName = end($path);
    $parentName = prev($path);

    foreach (new DirectoryIterator($directory->getPathname()) as $fileInfo) {
        if ($fileInfo->isDot() || strpos($fileInfo->getBasename(), '.') === 0) {
            continue;
        }
        
        if ($fileInfo->isDir()) {
            handleSubDirectoryAssets($fileInfo, $categories, $currentCategory, $templateName);
        } else {
            handleAssetSubDirectoryFile($fileInfo, $categories, $currentCategory, $templateName, $parentName, $directoryName);
        }
    }
}

function handleAssetSubDirectoryFile($fileInfo, &$categories, $currentCategory, $templateName, $parentName, $directoryName)
{
    $assetName = $fileInfo->getFilename();
    $assetName = substr($assetName, 0, strrpos($assetName, "."));
    
    array_push($categories[$currentCategory][$templateName][$parentName][$directoryName], $fileInfo->getPathname());
}

function mapContentToTemplateStructure($contentInfo, &$categories, $currentCategory, $templateName)
{
    $fileName = $contentInfo->getFilename();
    $fileName = substr($fileName, 0, strrpos($fileName, "."));

    switch ($fileName) {
        case "screen_export":
            $categories[$currentCategory][$templateName]['screen'] = $contentInfo->getPathname();
            break;
        case "screen-template-details":
            loadXmlAttributes($contentInfo, $categories, $currentCategory, $templateName);
            break;
    }
}

function loadXmlAttributes($contentInfo, &$categories, $currentCategory, $templateName)
{
    $xml = simplexml_load_file($contentInfo->getPathname());

    $name = (string) $xml->attributes()['name'];
    $description = (string) $xml->attributes()['description'];
    $screenType = (string) $xml->attributes()['screen_type'];
    $version = (string) $xml->attributes()['version'];
    

    $categories[$currentCategory][$templateName]['template_details']['name'] = $name;
    $categories[$currentCategory][$templateName]['template_details']['description'] = $description;
    $categories[$currentCategory][$templateName]['template_details']['screen_type'] = $screenType;
    $categories[$currentCategory][$templateName]['template_details']['version'] = $version;
}

// You also need to define the compute_hash and update_readme functions if they are not already defined.

function sort_categories(&$categories) {
    ksort($categories);
    foreach ($categories as &$category) {
        if (is_array($category)) {
            sort_categories($category);
        }
    }
}


main();
