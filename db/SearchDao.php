<?php

namespace nova\plugin\fullsearch\db;


use nova\plugin\fullsearch\QueryChinese;
use nova\plugin\orm\object\Dao;
use nova\plugin\orm\operation\InsertOperation;

class SearchDao extends Dao
{
    function addArticle($content, $article): void
    {


        $keywords = $this->split($article);

        $kvs = [];

        foreach ($keywords as $keyword) {
            $kvs[] = [
                 'content' => $content, 'keyword' => $keyword
            ];

        }
        $this->delete()->where([ 'content' => $content])->commit();
        if (!empty($kvs))
            $this->insert(InsertOperation::INSERT_IGNORE)->keyValues($kvs)->commit();
    }

    function deleteArticle( $content): void
    {
        $this->delete()->where([ 'content' => $content])->commit();
    }

    function search($keywords)
    {
        $keys = $this->split($keywords);
        if (empty($keys)) {
            return [];
        }
        $result = $this->select('content')
            ->where([' keyword in (:keys) ', ':keys' => $keys])
            ->groupBy('content')
            ->commit();
        return array_map(function ($item) {
            return $item['content'];
        }, $result);
    }

    //分词为数组
    private function split($content): array
    {
        $result = explode(' ', QueryChinese::get($this->tokenize($content)));
        $filteredArray = array_filter($result, function ($item) {
            return $item !== '' && !ctype_punct($item);
        });
        $uniqueArray = array_unique($filteredArray);

        $stopWordsPath = ROOT_PATH . DS . 'nova' . DS . 'plugin' . DS . 'fullsearch' . DS . 'stopwords.txt';
        $stopWords = file_exists($stopWordsPath) ? file($stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $stopSet = array_flip($stopWords);
        $tokens = array_values(array_filter($uniqueArray, function ($item) use ($stopSet) {
            return !isset($stopSet[$item]);
        }));

        return $tokens;
    }

    private function tokenize($text): string
    {

        $text = strip_tags($text);

        // 移除代码块与行内代码
        $text = preg_replace('/```[\s\S]*?```/u', ' ', $text);
        $text = preg_replace('/`[^`]*`/u', ' ', $text);

        // 移除 LaTeX 数学块与行内数学
        $text = preg_replace('/\$\$[\s\S]*?\$\$/u', ' ', $text);
        $text = preg_replace('/\$[^$]*\$/u', ' ', $text);

        // 移除图片与链接
        $text = preg_replace('/!\[[^\]]*\]\([^\)]+\)/u', ' ', $text);
        $text = preg_replace('/\[[^\]]*\]\([^\)]+\)/u', ' ', $text);

        // 删除标点
        $result = preg_replace('/[[:punct:]]/u', ' ', $text);

        return trim($result);
    }
}