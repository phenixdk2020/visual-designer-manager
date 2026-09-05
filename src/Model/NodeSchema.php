<?php

declare(strict_types=1);

namespace VisualDesignerManager\Model;

final class NodeSchema
{
    public const SECTION = 'section';
    public const CONTAINER = 'container';
    public const TEXT = 'text';
    public const IMAGE = 'image';
    public const BUTTON = 'button';
    public const LINK = 'link';
    public const ICON = 'icon';
    public const BADGE = 'badge';
    public const DATA_LIST = 'data-list';
    public const TABLE = 'table';
    public const SPACER = 'spacer';
    public const DIVIDER = 'divider';
    public const EVENTS = 'events';
    public const EVENT_VALUE = 'event-value';
    public const EVENT_IMAGE = 'event-image';
    public const EVENT_FIELD = 'event-field';
    public const EVENT_FACTS = 'event-facts';
    public const VEHICLES = 'vehicles';
    public const VEHICLE_DETAIL = 'vehicle-detail';
    public const GALLERIES = 'galleries';
    public const GALLERY_DETAIL = 'gallery-detail';
    public const CONTACT_FORM = 'contact-form';
    public const MEMBERSHIP_FORM = 'membership-form';
    public const NAVIGATION = 'navigation';

    /** @return list<string> */
    public static function types(): array
    {
        return [
            self::SECTION, self::CONTAINER, self::TEXT, self::IMAGE, self::BUTTON,
            self::LINK, self::ICON, self::BADGE, self::DATA_LIST, self::TABLE,
            self::SPACER, self::DIVIDER, self::EVENTS, self::EVENT_VALUE,
            self::EVENT_IMAGE, self::EVENT_FIELD, self::EVENT_FACTS,
            self::VEHICLES, self::VEHICLE_DETAIL, self::GALLERIES,
            self::GALLERY_DETAIL, self::CONTACT_FORM, self::MEMBERSHIP_FORM,
            self::NAVIGATION,
        ];
    }

    /** @return array<string,mixed> */
    public static function create(string $type, ?string $parentId = null): array
    {
        if (!in_array($type, self::types(), true)) {
            throw new \InvalidArgumentException('Unknown VDM node type.');
        }
        return [
            'id' => wp_generate_uuid4(),
            'type' => $type,
            'parentId' => $parentId,
            'order' => 0,
            'props' => self::defaultProps($type),
            'responsive' => [Breakpoint::DESKTOP => self::defaultGeometry($type)],
        ];
    }

    /** @param array<string,mixed> $node @return array<string,mixed> */
    public static function normalize(array $node): array
    {
        $type = sanitize_key((string) ($node['type'] ?? ''));
        if (!in_array($type, self::types(), true)) {
            throw new \InvalidArgumentException('Invalid VDM node type.');
        }
        $id = sanitize_key((string) ($node['id'] ?? ''));
        if ($id === '') {
            $id = wp_generate_uuid4();
        }
        $parentId = $node['parentId'] ?? null;
        if ($parentId !== null) {
            $parentId = sanitize_key((string) $parentId);
            $parentId = $parentId !== '' ? $parentId : null;
        }
        $responsive = [];
        $rawResponsive = is_array($node['responsive'] ?? null) ? $node['responsive'] : [];
        foreach (Breakpoint::ordered() as $breakpoint) {
            if (isset($rawResponsive[$breakpoint]) && is_array($rawResponsive[$breakpoint])) {
                $responsive[$breakpoint] = self::normalizeGeometry($rawResponsive[$breakpoint]);
            }
        }
        if (!isset($responsive[Breakpoint::DESKTOP])) {
            $responsive[Breakpoint::DESKTOP] = self::defaultGeometry($type);
        }
        return [
            'id' => $id,
            'type' => $type,
            'parentId' => $parentId,
            'order' => max(0, (int) ($node['order'] ?? 0)),
            'props' => self::normalizeProps($type, is_array($node['props'] ?? null) ? $node['props'] : []),
            'responsive' => $responsive,
        ];
    }

    /** @param array<string,mixed> $geometry @return array{x:int,y:int,w:int,h:int,fineX:int,fineW:int} */
    public static function normalizeGeometry(array $geometry): array
    {
        $x = max(0, min(11, (int) ($geometry['x'] ?? 0)));
        $w = max(1, min(12 - $x, (int) ($geometry['w'] ?? 12)));
        $fineX = max(0, min(119, (int) ($geometry['fineX'] ?? ($x * 10))));
        $fineW = max(1, min(120 - $fineX, (int) ($geometry['fineW'] ?? ($w * 10))));
        $x = max(0, min(11, intdiv($fineX, 10)));
        $w = max(1, min(12 - $x, (int) ceil($fineW / 10)));
        return [
            'x' => $x,
            'y' => max(0, min(2000, (int) ($geometry['y'] ?? 0))),
            'w' => $w,
            'h' => max(1, min(2000, (int) ($geometry['h'] ?? 4))),
            'fineX' => $fineX,
            'fineW' => $fineW,
        ];
    }

    /** @return array{x:int,y:int,w:int,h:int} */
    private static function defaultGeometry(string $type): array
    {
        return match ($type) {
            self::SECTION => ['x'=>0,'y'=>0,'w'=>12,'h'=>36],
            self::CONTAINER => ['x'=>0,'y'=>0,'w'=>12,'h'=>24],
            self::TEXT => ['x'=>0,'y'=>0,'w'=>6,'h'=>8],
            self::IMAGE => ['x'=>0,'y'=>0,'w'=>6,'h'=>18],
            self::BUTTON, self::LINK => ['x'=>0,'y'=>0,'w'=>3,'h'=>6],
            self::ICON => ['x'=>0,'y'=>0,'w'=>2,'h'=>8],
            self::BADGE => ['x'=>0,'y'=>0,'w'=>3,'h'=>5],
            self::DATA_LIST => ['x'=>0,'y'=>0,'w'=>6,'h'=>16],
            self::TABLE => ['x'=>0,'y'=>0,'w'=>12,'h'=>24],
            self::SPACER => ['x'=>0,'y'=>0,'w'=>12,'h'=>4],
            self::DIVIDER => ['x'=>0,'y'=>0,'w'=>12,'h'=>2],
            self::EVENTS, self::VEHICLES, self::GALLERIES => ['x'=>0,'y'=>0,'w'=>12,'h'=>60],
            self::EVENT_VALUE => ['x'=>0,'y'=>0,'w'=>6,'h'=>8],
            self::EVENT_IMAGE => ['x'=>0,'y'=>0,'w'=>12,'h'=>36],
            self::EVENT_FIELD => ['x'=>0,'y'=>0,'w'=>12,'h'=>16],
            self::EVENT_FACTS => ['x'=>0,'y'=>0,'w'=>12,'h'=>16],
            self::VEHICLE_DETAIL, self::GALLERY_DETAIL => ['x'=>0,'y'=>0,'w'=>12,'h'=>80],
            self::CONTACT_FORM => ['x'=>0,'y'=>0,'w'=>12,'h'=>100],
            self::MEMBERSHIP_FORM => ['x'=>0,'y'=>0,'w'=>12,'h'=>128],
            self::NAVIGATION => ['x'=>0,'y'=>0,'w'=>12,'h'=>8],
            default => ['x'=>0,'y'=>0,'w'=>12,'h'=>4],
        };
    }

    /** @return array<string,mixed> */
    private static function defaultProps(string $type): array
    {
        $commonLink = ['linkType'=>'url','pageId'=>0,'url'=>'#','anchor'=>'','email'=>'','phone'=>'','target'=>'_self'];
        return match ($type) {
            self::SECTION => ['background'=>'#ffffff','padding'=>0,'radius'=>0,'borderWidth'=>0,'borderColor'=>'#d0d0d0','autoHeight'=>true,'minHeightRows'=>36],
            self::CONTAINER => ['background'=>'transparent','padding'=>16,'radius'=>0,'borderWidth'=>0,'borderColor'=>'#d0d0d0','autoHeight'=>true,'minHeightRows'=>24],
            self::TEXT => ['content'=>'<p>Tekst</p>','color'=>'#222222','fontSize'=>18,'fontWeight'=>400,'lineHeight'=>1.5,'letterSpacing'=>0.0,'align'=>'left','verticalAlign'=>'top','background'=>'transparent','padding'=>0,'radius'=>0,'borderWidth'=>0,'borderColor'=>'#d0d0d0'],
            self::IMAGE => ['attachmentId'=>0,'alt'=>'','objectFit'=>'cover','positionX'=>'center','positionY'=>'center','radius'=>0,'borderWidth'=>0,'borderColor'=>'#d0d0d0'],
            self::BUTTON => array_merge($commonLink, ['label'=>'Knap','align'=>'left','background'=>'#2f4858','color'=>'#ffffff','hoverBackground'=>'#243946','hoverColor'=>'#ffffff','focusColor'=>'#ffffff','radius'=>4,'paddingX'=>18,'paddingY'=>10,'fontSize'=>16,'fontWeight'=>600,'borderWidth'=>0,'borderColor'=>'#2f4858','mode'=>'normal','zIndex'=>10,'autoSize'=>true]),
            self::LINK => array_merge($commonLink, ['label'=>'Link','align'=>'left','color'=>'#2271b1','hoverColor'=>'#135e96','fontSize'=>16,'fontWeight'=>400,'underline'=>true]),
            self::ICON => ['symbol'=>'★','ariaLabel'=>'','fontSize'=>36,'color'=>'#222222','background'=>'transparent','radius'=>0,'align'=>'center'],
            self::BADGE => ['label'=>'Badge','background'=>'#2f4858','color'=>'#ffffff','radius'=>999,'paddingX'=>10,'paddingY'=>5,'fontSize'=>13,'fontWeight'=>600,'align'=>'left'],
            self::DATA_LIST => ['items'=>[['label'=>'Felt','value'=>'Værdi']], 'labelColor'=>'#555555','valueColor'=>'#222222','dividerColor'=>'#d0d0d0','fontSize'=>16,'gap'=>8,'showDividers'=>true],
            self::TABLE => ['headers'=>['Kolonne 1','Kolonne 2'],'rows'=>[['Værdi 1','Værdi 2']], 'headerBackground'=>'#f0f0f1','headerColor'=>'#222222','cellBackground'=>'#ffffff','cellColor'=>'#222222','borderColor'=>'#d0d0d0','borderWidth'=>1,'fontSize'=>15,'striped'=>false],
            self::SPACER => [],
            self::DIVIDER => ['color'=>'#d0d0d0','thickness'=>1],
            self::EVENTS => ['count'=>6,'showPast'=>false,'columns'=>3,'gap'=>20,'padding'=>18,'radius'=>6,'cardBackground'=>'#ffffff','textColor'=>'#222222','headingColor'=>'#222222','accentColor'=>'#2f4858','showImage'=>true,'showSummary'=>true,'showFacts'=>true],
            self::EVENT_VALUE => ['field'=>'title','label'=>'','showLabel'=>false,'fontSize'=>24,'fontWeight'=>700,'color'=>'#222222','align'=>'left'],
            self::EVENT_IMAGE => ['size'=>'large','objectFit'=>'cover','positionX'=>'center','positionY'=>'center','radius'=>0],
            self::EVENT_FIELD => ['fieldId'=>'about','showHeading'=>true,'headingColor'=>'#222222','textColor'=>'#222222','headingSize'=>24,'bodySize'=>16,'background'=>'transparent','padding'=>0,'radius'=>0],
            self::EVENT_FACTS => ['showDate'=>true,'showTime'=>true,'showLocation'=>true,'showAddress'=>true,'showContact'=>true,'columns'=>5,'gap'=>8,'accentColor'=>'#2f4858','background'=>'#ffffff','textColor'=>'#222222'],
            self::VEHICLES => ['count'=>12,'columns'=>3,'gap'=>20,'padding'=>18,'radius'=>6,'cardBackground'=>'#ffffff','textColor'=>'#222222','headingColor'=>'#222222','accentColor'=>'#2f4858','showImage'=>true,'showSummary'=>true,'showFacts'=>true],
            self::VEHICLE_DETAIL => ['showImage'=>true,'showFacts'=>true,'showDescription'=>true,'accentColor'=>'#2f4858','imageRatio'=>'4/3'],
            self::GALLERIES => ['count'=>12,'columns'=>3,'gap'=>20,'padding'=>16,'radius'=>6,'cardBackground'=>'#ffffff','textColor'=>'#222222','headingColor'=>'#222222','accentColor'=>'#2f4858','showCover'=>true,'showSummary'=>true],
            self::GALLERY_DETAIL => ['columns'=>4,'gap'=>16,'showCaptions'=>true,'imageRatio'=>'4/3'],
            self::CONTACT_FORM => ['heading'=>'Kontakt os','intro'=>'Har du spørgsmål, er du velkommen til at kontakte os.','columns'=>2,'gap'=>16,'fieldGap'=>16,'padding'=>20,'radius'=>6,'background'=>'#ffffff','fieldBackground'=>'#ffffff','textColor'=>'#222222','labelColor'=>'#222222','borderColor'=>'#d0d0d0','accentColor'=>'#2f4858','buttonTextColor'=>'#ffffff','submitLabel'=>'Send besked','successMessage'=>'Tak. Din henvendelse er sendt.','showPhone'=>true,'showSubject'=>true,'showAddress'=>false,'showMessage'=>true,'messageRows'=>6,'textareaHeight'=>168,'requireConsent'=>true,'consentText'=>'Jeg accepterer, at oplysningerne bruges til at besvare min henvendelse.','consentMargin'=>18,'buttonPaddingX'=>20,'buttonPaddingY'=>11,'recipient'=>'','sendReceipt'=>true],
            self::MEMBERSHIP_FORM => ['heading'=>'Bliv medlem','intro'=>'Udfyld formularen, så kontakter vi dig om medlemskab.','columns'=>2,'gap'=>16,'fieldGap'=>16,'padding'=>20,'radius'=>6,'background'=>'#ffffff','fieldBackground'=>'#ffffff','textColor'=>'#222222','labelColor'=>'#222222','borderColor'=>'#d0d0d0','accentColor'=>'#2f4858','buttonTextColor'=>'#ffffff','submitLabel'=>'Send indmeldelse','successMessage'=>'Tak. Din indmeldelse er sendt.','showPhone'=>true,'showSubject'=>false,'showAddress'=>true,'showMessage'=>true,'messageRows'=>5,'textareaHeight'=>168,'requireConsent'=>true,'consentText'=>'Jeg accepterer, at oplysningerne bruges til at behandle min indmeldelse.','consentMargin'=>18,'buttonPaddingX'=>20,'buttonPaddingY'=>11,'recipient'=>'','sendReceipt'=>true],
            self::NAVIGATION => ['menuId'=>0,'orientation'=>'horizontal','align'=>'left','gap'=>24,'fontSize'=>16,'fontWeight'=>600,'textColor'=>'#222222','hoverColor'=>'#2271b1','background'=>'transparent','submenuBackground'=>'#ffffff','submenuTextColor'=>'#222222','toggleLabel'=>'Menu'],
            default => [],
        };
    }

    /** @param array<string,mixed> $props @return array<string,mixed> */
    private static function normalizeProps(string $type, array $props): array
    {
        $d = self::defaultProps($type);
        if (in_array($type, [self::SECTION, self::CONTAINER], true)) {
            return [
                'background'=>self::color((string)($props['background']??$d['background']),(string)$d['background']),
                'padding'=>self::int($props,'padding',(int)$d['padding'],0,120),
                'radius'=>self::int($props,'radius',(int)$d['radius'],0,80),
                'borderWidth'=>self::int($props,'borderWidth',(int)$d['borderWidth'],0,20),
                'borderColor'=>self::color((string)($props['borderColor']??$d['borderColor']),(string)$d['borderColor']),
                'autoHeight'=>!array_key_exists('autoHeight',$props)||!empty($props['autoHeight']),
                'minHeightRows'=>self::int($props,'minHeightRows',(int)$d['minHeightRows'],1,2000),
            ];
        }
        if ($type === self::TEXT) {
            return [
                'content'=>wp_kses_post((string)($props['content']??$d['content'])),
                'color'=>self::color((string)($props['color']??$d['color']),(string)$d['color']),
                'fontSize'=>self::int($props,'fontSize',(int)$d['fontSize'],8,120),
                'fontWeight'=>self::weight($props['fontWeight']??$d['fontWeight'],400),
                'lineHeight'=>self::float($props,'lineHeight',(float)$d['lineHeight'],0.8,3.0),
                'letterSpacing'=>self::float($props,'letterSpacing',(float)$d['letterSpacing'],-5,20),
                'align'=>self::choice((string)($props['align']??$d['align']),['left','center','right','justify'],'left'),
                'verticalAlign'=>self::choice((string)($props['verticalAlign']??$d['verticalAlign']),['top','center','bottom'],'top'),
                'background'=>self::color((string)($props['background']??$d['background']),(string)$d['background']),
                'padding'=>self::int($props,'padding',(int)$d['padding'],0,120),
                'radius'=>self::int($props,'radius',(int)$d['radius'],0,80),
                'borderWidth'=>self::int($props,'borderWidth',(int)$d['borderWidth'],0,20),
                'borderColor'=>self::color((string)($props['borderColor']??$d['borderColor']),(string)$d['borderColor']),
            ];
        }
        if ($type === self::IMAGE) {
            return [
                'attachmentId'=>absint($props['attachmentId']??0),'alt'=>sanitize_text_field((string)($props['alt']??'')),
                'objectFit'=>self::choice((string)($props['objectFit']??$d['objectFit']),['cover','contain','fill','none','scale-down'],'cover'),
                'positionX'=>self::choice((string)($props['positionX']??$d['positionX']),['left','center','right'],'center'),
                'positionY'=>self::choice((string)($props['positionY']??$d['positionY']),['top','center','bottom'],'center'),
                'radius'=>self::int($props,'radius',(int)$d['radius'],0,80),'borderWidth'=>self::int($props,'borderWidth',(int)$d['borderWidth'],0,20),
                'borderColor'=>self::color((string)($props['borderColor']??$d['borderColor']),(string)$d['borderColor']),
            ];
        }
        if (in_array($type,[self::BUTTON,self::LINK],true)) {
            $base = self::normalizeLinkProps($props,$d);
            if ($type === self::LINK) {
                return array_merge($base,[
                    'label'=>sanitize_text_field((string)($props['label']??$d['label'])),'align'=>self::choice((string)($props['align']??$d['align']),['left','center','right'],'left'),
                    'color'=>self::color((string)($props['color']??$d['color']),(string)$d['color']),'hoverColor'=>self::color((string)($props['hoverColor']??$d['hoverColor']),(string)$d['hoverColor']),
                    'fontSize'=>self::int($props,'fontSize',(int)$d['fontSize'],8,80),'fontWeight'=>self::weight($props['fontWeight']??$d['fontWeight'],400),'underline'=>!array_key_exists('underline',$props)||!empty($props['underline']),
                ]);
            }
            return array_merge($base,[
                'label'=>sanitize_text_field((string)($props['label']??$d['label'])),'align'=>self::choice((string)($props['align']??$d['align']),['left','center','right','stretch'],'left'),
                'background'=>self::color((string)($props['background']??$d['background']),(string)$d['background']),'color'=>self::color((string)($props['color']??$d['color']),(string)$d['color']),
                'hoverBackground'=>self::color((string)($props['hoverBackground']??$d['hoverBackground']),(string)$d['hoverBackground']),'hoverColor'=>self::color((string)($props['hoverColor']??$d['hoverColor']),(string)$d['hoverColor']),
                'focusColor'=>self::color((string)($props['focusColor']??$d['focusColor']),(string)$d['focusColor']),'radius'=>self::int($props,'radius',(int)$d['radius'],0,80),
                'paddingX'=>self::int($props,'paddingX',(int)$d['paddingX'],0,120),'paddingY'=>self::int($props,'paddingY',(int)$d['paddingY'],0,80),
                'fontSize'=>self::int($props,'fontSize',(int)$d['fontSize'],8,80),'fontWeight'=>self::weight($props['fontWeight']??$d['fontWeight'],600),
                'borderWidth'=>self::int($props,'borderWidth',(int)$d['borderWidth'],0,20),'borderColor'=>self::color((string)($props['borderColor']??$d['borderColor']),(string)$d['borderColor']),
                'mode'=>self::choice((string)($props['mode']??$d['mode']),['normal','floating'],'normal'),'zIndex'=>self::int($props,'zIndex',(int)$d['zIndex'],1,200),'autoSize'=>!array_key_exists('autoSize',$props)||!empty($props['autoSize']),
            ]);
        }
        if ($type === self::ICON) {
            return ['symbol'=>sanitize_text_field((string)($props['symbol']??$d['symbol'])),'ariaLabel'=>sanitize_text_field((string)($props['ariaLabel']??'')),'fontSize'=>self::int($props,'fontSize',(int)$d['fontSize'],8,180),'color'=>self::color((string)($props['color']??$d['color']),(string)$d['color']),'background'=>self::color((string)($props['background']??$d['background']),(string)$d['background']),'radius'=>self::int($props,'radius',(int)$d['radius'],0,80),'align'=>self::choice((string)($props['align']??$d['align']),['left','center','right'],'center')];
        }
        if ($type === self::BADGE) {
            return ['label'=>sanitize_text_field((string)($props['label']??$d['label'])),'background'=>self::color((string)($props['background']??$d['background']),(string)$d['background']),'color'=>self::color((string)($props['color']??$d['color']),(string)$d['color']),'radius'=>self::int($props,'radius',(int)$d['radius'],0,999),'paddingX'=>self::int($props,'paddingX',(int)$d['paddingX'],0,80),'paddingY'=>self::int($props,'paddingY',(int)$d['paddingY'],0,60),'fontSize'=>self::int($props,'fontSize',(int)$d['fontSize'],8,60),'fontWeight'=>self::weight($props['fontWeight']??$d['fontWeight'],600),'align'=>self::choice((string)($props['align']??$d['align']),['left','center','right'],'left')];
        }
        if ($type === self::DATA_LIST) {
            $items=[]; foreach(array_slice((array)($props['items']??$d['items']),0,100) as $row){if(!is_array($row))continue;$items[]=['label'=>sanitize_text_field((string)($row['label']??'')),'value'=>sanitize_text_field((string)($row['value']??''))];}
            return ['items'=>$items,'labelColor'=>self::color((string)($props['labelColor']??$d['labelColor']),(string)$d['labelColor']),'valueColor'=>self::color((string)($props['valueColor']??$d['valueColor']),(string)$d['valueColor']),'dividerColor'=>self::color((string)($props['dividerColor']??$d['dividerColor']),(string)$d['dividerColor']),'fontSize'=>self::int($props,'fontSize',(int)$d['fontSize'],8,60),'gap'=>self::int($props,'gap',(int)$d['gap'],0,60),'showDividers'=>!array_key_exists('showDividers',$props)||!empty($props['showDividers'])];
        }
        if ($type === self::TABLE) {
            $headers=array_slice(array_map(static fn($v):string=>sanitize_text_field((string)$v),(array)($props['headers']??$d['headers'])),0,20);$rows=[];foreach(array_slice((array)($props['rows']??$d['rows']),0,100) as $row){if(!is_array($row))continue;$rows[]=array_slice(array_map(static fn($v):string=>sanitize_text_field((string)$v),$row),0,20);} return ['headers'=>$headers,'rows'=>$rows,'headerBackground'=>self::color((string)($props['headerBackground']??$d['headerBackground']),(string)$d['headerBackground']),'headerColor'=>self::color((string)($props['headerColor']??$d['headerColor']),(string)$d['headerColor']),'cellBackground'=>self::color((string)($props['cellBackground']??$d['cellBackground']),(string)$d['cellBackground']),'cellColor'=>self::color((string)($props['cellColor']??$d['cellColor']),(string)$d['cellColor']),'borderColor'=>self::color((string)($props['borderColor']??$d['borderColor']),(string)$d['borderColor']),'borderWidth'=>self::int($props,'borderWidth',(int)$d['borderWidth'],0,10),'fontSize'=>self::int($props,'fontSize',(int)$d['fontSize'],8,40),'striped'=>!empty($props['striped'])];
        }
        if ($type === self::EVENTS) {
            return self::normalizeCards($props,$d,24,true);
        }
        if ($type === self::VEHICLES) {
            return self::normalizeCards($props,$d,50,false);
        }
        if ($type === self::GALLERIES) {
            $base=self::normalizeCards($props,$d,50,false); unset($base['showImage'],$base['showFacts']); $base['showCover']=!array_key_exists('showCover',$props)||!empty($props['showCover']); return $base;
        }
        if ($type === self::EVENT_VALUE) {
            return ['field'=>self::choice((string)($props['field']??$d['field']),['title','date','time','location','address','contact','summary','description'],'title'),'label'=>sanitize_text_field((string)($props['label']??'')),'showLabel'=>!empty($props['showLabel']),'fontSize'=>self::int($props,'fontSize',(int)$d['fontSize'],8,100),'fontWeight'=>self::weight($props['fontWeight']??$d['fontWeight'],700),'color'=>self::color((string)($props['color']??$d['color']),(string)$d['color']),'align'=>self::choice((string)($props['align']??$d['align']),['left','center','right'],'left')];
        }
        if ($type === self::EVENT_IMAGE) {
            return ['size'=>self::choice((string)($props['size']??$d['size']),['thumbnail','medium','large','full'],'large'),'objectFit'=>self::choice((string)($props['objectFit']??$d['objectFit']),['cover','contain'],'cover'),'positionX'=>self::choice((string)($props['positionX']??$d['positionX']),['left','center','right'],'center'),'positionY'=>self::choice((string)($props['positionY']??$d['positionY']),['top','center','bottom'],'center'),'radius'=>self::int($props,'radius',(int)$d['radius'],0,80)];
        }
        if ($type === self::EVENT_FIELD) {
            return ['fieldId'=>sanitize_key((string)($props['fieldId']??$d['fieldId'])),'showHeading'=>!array_key_exists('showHeading',$props)||!empty($props['showHeading']),'headingColor'=>self::color((string)($props['headingColor']??$d['headingColor']),(string)$d['headingColor']),'textColor'=>self::color((string)($props['textColor']??$d['textColor']),(string)$d['textColor']),'headingSize'=>self::int($props,'headingSize',(int)$d['headingSize'],8,80),'bodySize'=>self::int($props,'bodySize',(int)$d['bodySize'],8,60),'background'=>self::color((string)($props['background']??$d['background']),(string)$d['background']),'padding'=>self::int($props,'padding',(int)$d['padding'],0,120),'radius'=>self::int($props,'radius',(int)$d['radius'],0,80)];
        }
        if ($type === self::EVENT_FACTS) {
            return ['showDate'=>!array_key_exists('showDate',$props)||!empty($props['showDate']),'showTime'=>!array_key_exists('showTime',$props)||!empty($props['showTime']),'showLocation'=>!array_key_exists('showLocation',$props)||!empty($props['showLocation']),'showAddress'=>!array_key_exists('showAddress',$props)||!empty($props['showAddress']),'showContact'=>!array_key_exists('showContact',$props)||!empty($props['showContact']),'columns'=>self::int($props,'columns',(int)$d['columns'],1,5),'gap'=>self::int($props,'gap',(int)$d['gap'],0,60),'accentColor'=>self::color((string)($props['accentColor']??$d['accentColor']),(string)$d['accentColor']),'background'=>self::color((string)($props['background']??$d['background']),(string)$d['background']),'textColor'=>self::color((string)($props['textColor']??$d['textColor']),(string)$d['textColor'])];
        }
        if ($type === self::VEHICLE_DETAIL) {
            return ['showImage'=>!array_key_exists('showImage',$props)||!empty($props['showImage']),'showFacts'=>!array_key_exists('showFacts',$props)||!empty($props['showFacts']),'showDescription'=>!array_key_exists('showDescription',$props)||!empty($props['showDescription']),'accentColor'=>self::color((string)($props['accentColor']??$d['accentColor']),(string)$d['accentColor']),'imageRatio'=>self::choice((string)($props['imageRatio']??$d['imageRatio']),['16/9','4/3','3/2','1/1'],'4/3')];
        }
        if ($type === self::GALLERY_DETAIL) {
            return ['columns'=>self::int($props,'columns',(int)$d['columns'],1,6),'gap'=>self::int($props,'gap',(int)$d['gap'],0,80),'showCaptions'=>!array_key_exists('showCaptions',$props)||!empty($props['showCaptions']),'imageRatio'=>self::choice((string)($props['imageRatio']??$d['imageRatio']),['16/9','4/3','3/2','1/1'],'4/3')];
        }
        if (in_array($type,[self::CONTACT_FORM,self::MEMBERSHIP_FORM],true)) {
            return ['heading'=>sanitize_text_field((string)($props['heading']??$d['heading'])),'intro'=>sanitize_textarea_field((string)($props['intro']??$d['intro'])),'columns'=>self::int($props,'columns',(int)$d['columns'],1,2),'gap'=>self::int($props,'gap',(int)$d['gap'],0,60),'fieldGap'=>self::int($props,'fieldGap',(int)$d['fieldGap'],0,80),'padding'=>self::int($props,'padding',(int)$d['padding'],0,80),'radius'=>self::int($props,'radius',(int)$d['radius'],0,30),'background'=>self::color((string)($props['background']??$d['background']),(string)$d['background']),'fieldBackground'=>self::color((string)($props['fieldBackground']??$d['fieldBackground']),(string)$d['fieldBackground']),'textColor'=>self::color((string)($props['textColor']??$d['textColor']),(string)$d['textColor']),'labelColor'=>self::color((string)($props['labelColor']??$d['labelColor']),(string)$d['labelColor']),'borderColor'=>self::color((string)($props['borderColor']??$d['borderColor']),(string)$d['borderColor']),'accentColor'=>self::color((string)($props['accentColor']??$d['accentColor']),(string)$d['accentColor']),'buttonTextColor'=>self::color((string)($props['buttonTextColor']??$d['buttonTextColor']),(string)$d['buttonTextColor']),'submitLabel'=>sanitize_text_field((string)($props['submitLabel']??$d['submitLabel'])),'successMessage'=>sanitize_text_field((string)($props['successMessage']??$d['successMessage'])),'showPhone'=>!array_key_exists('showPhone',$props)||!empty($props['showPhone']),'showSubject'=>!empty($props['showSubject']),'showAddress'=>!empty($props['showAddress']),'showMessage'=>!array_key_exists('showMessage',$props)||!empty($props['showMessage']),'messageRows'=>self::int($props,'messageRows',(int)$d['messageRows'],3,12),'textareaHeight'=>self::int($props,'textareaHeight',(int)$d['textareaHeight'],80,500),'requireConsent'=>!array_key_exists('requireConsent',$props)||!empty($props['requireConsent']),'consentText'=>sanitize_text_field((string)($props['consentText']??$d['consentText'])),'consentMargin'=>self::int($props,'consentMargin',(int)$d['consentMargin'],0,80),'buttonPaddingX'=>self::int($props,'buttonPaddingX',(int)$d['buttonPaddingX'],0,80),'buttonPaddingY'=>self::int($props,'buttonPaddingY',(int)$d['buttonPaddingY'],0,60),'recipient'=>sanitize_email((string)($props['recipient']??'')),'sendReceipt'=>!array_key_exists('sendReceipt',$props)||!empty($props['sendReceipt'])];
        }
        if ($type === self::NAVIGATION) {
            return ['menuId'=>absint($props['menuId']??0),'orientation'=>self::choice((string)($props['orientation']??$d['orientation']),['horizontal','vertical'],'horizontal'),'align'=>self::choice((string)($props['align']??$d['align']),['left','center','right'],'left'),'gap'=>self::int($props,'gap',(int)$d['gap'],0,80),'fontSize'=>self::int($props,'fontSize',(int)$d['fontSize'],10,40),'fontWeight'=>self::weight($props['fontWeight']??$d['fontWeight'],600),'textColor'=>self::color((string)($props['textColor']??$d['textColor']),(string)$d['textColor']),'hoverColor'=>self::color((string)($props['hoverColor']??$d['hoverColor']),(string)$d['hoverColor']),'background'=>self::color((string)($props['background']??$d['background']),(string)$d['background']),'submenuBackground'=>self::color((string)($props['submenuBackground']??$d['submenuBackground']),(string)$d['submenuBackground']),'submenuTextColor'=>self::color((string)($props['submenuTextColor']??$d['submenuTextColor']),(string)$d['submenuTextColor']),'toggleLabel'=>sanitize_text_field((string)($props['toggleLabel']??$d['toggleLabel'])) ?: 'Menu'];
        }
        if ($type === self::DIVIDER) {
            return ['color'=>self::color((string)($props['color']??$d['color']),(string)$d['color']),'thickness'=>self::int($props,'thickness',(int)$d['thickness'],1,20)];
        }
        return [];
    }

    /** @param array<string,mixed> $props @param array<string,mixed> $d @return array<string,mixed> */
    private static function normalizeLinkProps(array $props,array $d):array
    {
        return ['linkType'=>self::choice((string)($props['linkType']??$d['linkType']),['url','page','anchor','email','tel'],'url'),'pageId'=>absint($props['pageId']??0),'url'=>esc_url_raw((string)($props['url']??$d['url'])),'anchor'=>sanitize_text_field((string)($props['anchor']??'')),'email'=>sanitize_email((string)($props['email']??'')),'phone'=>sanitize_text_field((string)($props['phone']??'')),'target'=>self::choice((string)($props['target']??$d['target']),['_self','_blank'],'_self')];
    }

    /** @param array<string,mixed> $props @param array<string,mixed> $d @return array<string,mixed> */
    private static function normalizeCards(array $props,array $d,int $max,bool $events):array
    {
        $out=['count'=>self::int($props,'count',(int)$d['count'],1,$max),'columns'=>self::int($props,'columns',(int)$d['columns'],1,4),'gap'=>self::int($props,'gap',(int)$d['gap'],0,80),'padding'=>self::int($props,'padding',(int)$d['padding'],0,80),'radius'=>self::int($props,'radius',(int)$d['radius'],0,60),'cardBackground'=>self::color((string)($props['cardBackground']??$d['cardBackground']),(string)$d['cardBackground']),'textColor'=>self::color((string)($props['textColor']??$d['textColor']),(string)$d['textColor']),'headingColor'=>self::color((string)($props['headingColor']??$d['headingColor']),(string)$d['headingColor']),'accentColor'=>self::color((string)($props['accentColor']??$d['accentColor']),(string)$d['accentColor']),'showImage'=>!array_key_exists('showImage',$props)||!empty($props['showImage']),'showSummary'=>!array_key_exists('showSummary',$props)||!empty($props['showSummary']),'showFacts'=>!array_key_exists('showFacts',$props)||!empty($props['showFacts'])];
        if($events){$out['showPast']=!empty($props['showPast']);}
        return $out;
    }

    private static function color(string $value,string $fallback):string
    {
        if($value==='transparent'){return 'transparent';}$color=sanitize_hex_color($value);return is_string($color)?$color:$fallback;
    }
    private static function int(array $props,string $key,int $default,int $min,int $max):int{return max($min,min($max,(int)($props[$key]??$default)));}
    private static function float(array $props,string $key,float $default,float $min,float $max):float{return max($min,min($max,(float)($props[$key]??$default)));}
    /** @param list<string> $allowed */ private static function choice(string $value,array $allowed,string $fallback):string{return in_array($value,$allowed,true)?$value:$fallback;}
    private static function weight(mixed $value,int $fallback):int{$weight=(int)$value;return in_array($weight,[300,400,500,600,700,800,900],true)?$weight:$fallback;}

    private function __construct(){}
}
