<?php
declare(strict_types=1);

namespace CajunWebPro\Foundation\Projects;

if (PHP_SAPI !== 'cli' && !defined('CWP_BOOT')) { http_response_code(404); exit; }

use InvalidArgumentException;

/** Privacy-minimal identity for one currently actionable project review. */
final readonly class ProjectReviewAction
{
    public function __construct(
        public int $reviewId,
        public string $kind,
        public int $version,
    ) {
        if ($reviewId < 1 || $reviewId > 4294967295
            || $version < 1 || $version > 4294967295
            || !in_array($kind, ['design', 'proposal'], true)) {
            throw new InvalidArgumentException('Invalid project review action.');
        }
    }
}

/**
 * Derive latest pending review actions from already-authorized project rows.
 * No title, body, feedback, client identity, billing data or route is retained.
 */
final class ProjectActionItems
{
    public const MAX_REVIEW_ROWS = 1000;

    /**
     * @param list<array{id:int,kind:string,version_no:int,decision:string}> $rows
     * @return list<ProjectReviewAction> newest database identity first
     */
    public static function pendingReviews(array $rows): array
    {
        if (!array_is_list($rows) || count($rows) > self::MAX_REVIEW_ROWS) {
            throw new InvalidArgumentException('Use a bounded list of project review rows.');
        }
        $seenIds=[];$seenVersions=[];$latest=[];
        foreach ($rows as $row) {
            if (!is_array($row) || array_keys($row)!==['id','kind','version_no','decision']) {
                throw new InvalidArgumentException('Invalid project review row.');
            }
            ['id'=>$id,'kind'=>$kind,'version_no'=>$version,'decision'=>$decision]=$row;
            if (!is_int($id) || $id<1 || $id>4294967295
                || !is_int($version) || $version<1 || $version>4294967295
                || !is_string($kind) || !in_array($kind,['design','proposal'],true)
                || !is_string($decision) || !in_array($decision,['pending','approved','changes'],true)) {
                throw new InvalidArgumentException('Invalid project review row.');
            }
            if (isset($seenIds[$id]) || isset($seenVersions[$kind][$version])) {
                throw new InvalidArgumentException('Duplicate project review identity.');
            }
            $seenIds[$id]=true;$seenVersions[$kind][$version]=true;
            if (!isset($latest[$kind]) || $version>$latest[$kind]['version_no']) $latest[$kind]=$row;
        }
        $actions=[];
        foreach ($latest as $row) if ($row['decision']==='pending') {
            $actions[]=new ProjectReviewAction($row['id'],$row['kind'],$row['version_no']);
        }
        usort($actions,static fn(ProjectReviewAction $a,ProjectReviewAction $b):int=>$b->reviewId<=>$a->reviewId);
        return $actions;
    }
}
