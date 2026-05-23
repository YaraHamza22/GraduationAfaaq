<?php
namespace Modules\UserMangementModule\Enums;

enum EducationalLevel: string
{
    case HIGHSCHOOL = 'highschool';
    case ASSOCIATE = 'associate';
    case BACHELOR = 'bachelor';
    case COLLAGE = 'collage';
    case MASTER = 'master';
    case DOCTORATE = 'doctorate';
    case OTHER = 'other';
}
