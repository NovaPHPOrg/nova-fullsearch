<?php

namespace nova\plugin\fullsearch\db;

use nova\plugin\fullsearch\QueryChinese;
use nova\plugin\orm\object\Dao;
use nova\plugin\orm\operation\InsertOperation;
use function nova\framework\dump;

/**
 * 全文搜索数据访问对象
 * 
 * 负责文章索引的增删改查，以及中文分词和关键词提取
 * 数据结构：content -> keyword 的倒排索引
 */
class SearchDao extends Dao
{
    /**
     * 添加或更新文章索引
     * 
     * 先删除该文章的所有旧索引，然后重新建立索引
     * 使用 INSERT_IGNORE 避免重复关键词导致的错误
     * 
     * @param string $articleText 文章全文内容（用于分词）
     * @param string $contentId 文章唯一标识（通常是文档ID，用于数据库存储）
     * @return void
     */
    public function addArticle(string $articleText, string $contentId): void
    {
        $keywords = $this->split($articleText);
        $keywordRecords = [];

        foreach ($keywords as $keyword) {
            $keywordRecords[] = [
                'content' => $contentId,
                'keyword' => $keyword
            ];
        }

        // 先删除旧索引，再插入新索引
        $this->deleteArticle($contentId);
        
        if (!empty($keywordRecords)) {
            $this->insert(InsertOperation::INSERT_IGNORE)
                ->keyValues($keywordRecords)
                ->commit();
        }
    }

    /**
     * 删除文章索引
     * 
     * @param string $contentId 文章唯一标识
     * @return void
     */
    public function deleteArticle(string $contentId): void
    {
        $this->delete()->where(['content' => $contentId])->commit();
    }

    /**
     * 搜索包含关键词的文章
     * 
     * 将搜索关键词分词后，查找包含任意关键词的文章ID列表
     * 使用 GROUP BY 确保每个文章只返回一次
     * 
     * @param string $searchKeywords 搜索关键词
     * @return array<string> 匹配的文章ID数组
     */
    public function search(string $searchKeywords): array
    {
        $searchKeywordTokens = $this->split($searchKeywords);
        
        if (empty($searchKeywordTokens)) {
            return [];
        }

        $queryResults = $this->select('content')
            ->where(['keyword in (:keywords)', ':keywords' => $searchKeywordTokens])
            ->groupBy('content')
            ->commit();

        return array_map(function ($row) {
            return $row->content;
        }, $queryResults);
    }

    /**
     * 将文本内容分词为关键词数组
     * 
     * 处理流程：
     * 1. 预处理文本（移除HTML标签、代码块、数学公式、图片链接等）
     * 2. 中文分词
     * 3. 过滤空字符串和标点符号
     * 4. 去重
     * 5. 过滤停用词
     * 
     * @param string $content 待分词的内容文本
     * @return array<string> 关键词数组
     */
    private function split(string $content): array
    {
        $preprocessedText = $this->tokenize($content);
        $segmentedText = QueryChinese::get($preprocessedText);
        $rawTokens = explode(' ', $segmentedText);

        // 过滤空字符串和标点符号
        $filteredTokens = array_filter($rawTokens, function ($token) {
            return $token !== '' && !ctype_punct($token);
        });

        // 去重
        $uniqueTokens = array_unique($filteredTokens);

        // 过滤停用词
        $stopWordsPath = ROOT_PATH . DS . 'nova' . DS . 'plugin' . DS . 'fullsearch' . DS . 'stopwords.txt';
        $stopWords = file_exists($stopWordsPath)
            ? file($stopWordsPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : [];
        $stopWordsSet = array_flip($stopWords);

        $finalTokens = array_values(array_filter($uniqueTokens, function ($token) use ($stopWordsSet) {
            return !isset($stopWordsSet[$token]);
        }));

        return $finalTokens;
    }

    /**
     * 文本预处理：移除不需要索引的内容
     * 
     * 移除的内容包括：
     * - HTML标签
     * - Markdown代码块（```代码```）
     * - 行内代码（`代码`）
     * - LaTeX数学块（$$公式$$）
     * - 行内数学公式（$公式$）
     * - Markdown图片（![alt](url)）
     * - Markdown链接（[text](url)）
     * - 标点符号
     * 
     * @param string $text 原始文本
     * @return string 预处理后的文本
     */
    private function tokenize(string $text): string
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

        // 删除标点符号
        $cleanedText = preg_replace('/[[:punct:]]/u', ' ', $text);

        return trim($cleanedText);
    }
}