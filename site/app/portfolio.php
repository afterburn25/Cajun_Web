<?php
/** Public portfolio adapter: projects-table rows become foundation contract records. */
declare(strict_types=1);
defined('CWP_BOOT') || exit;

use CajunWebPro\Foundation\Portfolio\CaseStudy;
use CajunWebPro\Foundation\Portfolio\PortfolioCatalog;
use CajunWebPro\Foundation\Portfolio\PortfolioItem;
use CajunWebPro\Foundation\Portfolio\PortfolioMedia;
use CajunWebPro\Foundation\Portfolio\PortfolioQuery;

foreach (['portfolio/PortfolioMedia','portfolio/BeforeAfterPair','portfolio/CaseStudy','portfolio/PortfolioItem','portfolio/PortfolioQuery','portfolio/PortfolioCatalog'] as $class) {
    require_once __DIR__.'/foundation/'.$class.'.php';
}

/** @param array<string,mixed> $row One row from the projects table. */
function portfolio_item(array $row): PortfolioItem {
    $deliverables=array_values(array_filter(array_map('trim',preg_split('/\R/',(string)($row['deliverables']??''))?:[]),fn(string $d)=>$d!==''));
    $category=trim((string)($row['category']??''));
    $live=trim((string)($row['website_url']??''));
    return new PortfolioItem(
        id:(string)$row['id'],
        slug:(string)$row['slug'],
        title:(string)$row['title'],
        summary:(string)($row['summary']??''),
        kind:(int)($row['is_demo']??0)===1?PortfolioItem::KIND_CONCEPT:PortfolioItem::KIND_CLIENT_WORK,
        status:match($row['status']??''){
            PortfolioItem::STATUS_PUBLISHED=>PortfolioItem::STATUS_PUBLISHED,
            PortfolioItem::STATUS_ARCHIVED=>PortfolioItem::STATUS_ARCHIVED,
            default=>PortfolioItem::STATUS_DRAFT},
        categories:$category!==''?[$category]:[],
        position:(int)($row['sort_order']??0),
        liveUrl:$live!==''?$live:null,
        cover:new PortfolioMedia(image_url($row),trim((string)$row['title']).' website design'),
        caseStudy:new CaseStudy((string)($row['challenge']??''),(string)($row['solution']??''),$deliverables,''),
    );
}

/**
 * Validated contract items plus their source rows keyed by project id.
 * Malformed published rows are logged and skipped rather than blanking the page.
 *
 * @return array{0:list<PortfolioItem>,1:array<string,array>}
 */
function portfolio_set(): array {
    $rows=[];$items=[];
    foreach(all('SELECT * FROM '.table('projects')." WHERE status='published'") as $row){
        try{$item=portfolio_item($row);$items[]=$item;$row['category']=$item->categories[0]??'';$rows[$item->id]=$row;}catch(InvalidArgumentException $e){report_error($e);}
    }
    return [$items,$rows];
}

/**
 * Ordered published rows, the visible subset for a requested category and the
 * live category list. "visible" is null when unfiltered and an empty list when
 * a real request matched nothing.
 *
 * @return array{rows:list<array>,visible:?list<int>,categories:list<string>}
 */
function portfolio_view(?string $category): array {
    [$items,$rows]=portfolio_set();$catalog=new PortfolioCatalog();
    $ordered=array_map(fn(PortfolioItem $i)=>$rows[$i->id],$catalog->publishedItems($items));
    $visible=null;
    if($category!==null&&trim($category)!=='')
        $visible=array_map(fn(PortfolioItem $i)=>(int)$i->id,$catalog->publishedItems($items,PortfolioQuery::forCategory($category)));
    return ['rows'=>$ordered,'visible'=>$visible,'categories'=>$catalog->publishedCategories($items)];
}

/** One published project row by exact slug; draft, archived and unknown slugs return null. */
function portfolio_row_by_slug(string $slug): ?array {
    [$items,$rows]=portfolio_set();
    $item=(new PortfolioCatalog())->publishedBySlug($items,$slug);
    return $item===null?null:$rows[$item->id];
}
