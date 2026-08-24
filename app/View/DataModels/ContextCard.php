<?php

namespace App\View\DataModels;

use App\Helpers\DataModel;
use Zerotoprod\DataModel\Describe;

#[Describe([Describe::nullable => true])]
readonly class ContextCard
{
    use DataModel;

    public const string heading = 'heading';

    #[Describe([Describe::required => true])]
    public string $heading;

    public const string title = 'title';

    public ?string $title;

    /** @return array<string, mixed> */
    public function pageHeader(): array
    {
        return [PageHeader::title => $this->title];
    }
}
