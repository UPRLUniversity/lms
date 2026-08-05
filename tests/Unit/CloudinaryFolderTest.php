<?php

namespace Tests\Unit;

use App\Enums\MediaPurpose;
use App\Services\Media\CloudinaryMediaService;
use Cloudinary\Cloudinary;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Public images are filed under "<root>/<purpose>" so nothing lands loose at the
 * Cloudinary account root, and a staging deployment can share one account under its own
 * root without its uploads mixing into the live library.
 */
class CloudinaryFolderTest extends TestCase
{
    private function folderFor(MediaPurpose $purpose): string
    {
        $service = new CloudinaryMediaService(new Cloudinary('cloudinary://key:secret@cloud'));

        $method = new ReflectionMethod($service, 'folderFor');
        $method->setAccessible(true);

        return $method->invoke($service, $purpose);
    }

    public function test_each_purpose_gets_its_own_folder_under_the_root(): void
    {
        config(['media.root_folder' => 'uprl']);

        $this->assertSame('uprl/course_covers', $this->folderFor(MediaPurpose::CourseCovers));
        $this->assertSame('uprl/avatars', $this->folderFor(MediaPurpose::Avatars));
    }

    public function test_the_root_is_configurable_so_staging_can_share_an_account(): void
    {
        config(['media.root_folder' => 'UPRL_LMS_staging']);

        $this->assertSame('UPRL_LMS_staging/avatars', $this->folderFor(MediaPurpose::Avatars));
    }

    public function test_a_blank_or_slashed_root_never_produces_a_leading_slash(): void
    {
        config(['media.root_folder' => '/uprl/']);
        $this->assertSame('uprl/avatars', $this->folderFor(MediaPurpose::Avatars));

        config(['media.root_folder' => '']);
        $this->assertSame('avatars', $this->folderFor(MediaPurpose::Avatars));
    }
}
