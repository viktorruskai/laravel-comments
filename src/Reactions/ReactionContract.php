<?php

namespace LakM\Comments\Reactions;

use LakM\Comments\Models\Comment;

abstract class ReactionContract
{
    public function __construct(protected Comment $comment, protected ?string $type = null)
    {}
}