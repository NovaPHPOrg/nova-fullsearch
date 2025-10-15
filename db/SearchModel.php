<?php

namespace nova\plugin\fullsearch\db;

use nova\plugin\orm\object\Model;

class SearchModel extends Model
{
    public string $keyword = ""; //关键字
    public int $content = 0; //关键字关联的content

   
}