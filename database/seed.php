<?php
function seed_store(): void {
    $settings=[
        'branding'=>['name'=>"M's Charm",'slogan'=>'Accessories & More','primary'=>'#425c4b','secondary'=>'#ffffff','accent'=>'#dfd8c8','logo'=>'','favicon'=>'/assets/favicon.svg'],
        'contact'=>['email'=>'','phone'=>'','whatsapp'=>'','address'=>'','city'=>'','hours'=>'','heading'=>'We would love to hear from you','description'=>'Questions about a piece, your order, or finding the perfect gift? Send us a note.','form_enabled'=>true],
        'social'=>['instagram'=>'','facebook'=>'','tiktok'=>'','whatsapp'=>'','other'=>''],
        'store'=>['currency'=>'USD','delivery_fee'=>5,'free_delivery_threshold'=>75,'minimum_order'=>0,'tax_rate'=>0,'open'=>true,'delivery_description'=>'Carefully packed and delivered to your door.','payment_label'=>'Cash on delivery','announcement'=>'Little details. Lasting impressions.','announcement_enabled'=>true],
        'footer'=>['text'=>'Thoughtfully chosen accessories for the moments that make you, you.','copyright'=>"M's Charm. All rights reserved."],
        'seo'=>['title'=>"M's Charm | Accessories & More",'description'=>'Discover thoughtfully chosen jewelry and accessories at M’s Charm. Find your everyday charm.','sharing_image'=>''],
    ];
    foreach($settings as $id=>$value) insert_record('settings',['id'=>$id,'value'=>json_encode($value),'updated_at'=>now()]);
    $imgs=[
        'bracelets'=>'https://images.pexels.com/photos/34372556/pexels-photo-34372556.jpeg?auto=compress&cs=tinysrgb&w=1400',
        'rings'=>'https://images.unsplash.com/photo-1731586249471-82bb9b2f769a?auto=format&fit=crop&w=1400&q=85',
        'necklaces'=>'https://images.unsplash.com/photo-1569397288884-4d43d6738fbd?auto=format&fit=crop&w=1400&q=85',
        'earrings'=>'https://images.pexels.com/photos/7419523/pexels-photo-7419523.jpeg?auto=compress&cs=tinysrgb&w=1400',
    ];
    $categories=['Jewelry','Bracelets','Necklaces','Rings','Earrings','Hair Accessories','Other Accessories']; $ids=[];
    foreach($categories as $i=>$name) { $id=uuid();$key=slug($name);$ids[$key]=$id;insert_record('categories',['id'=>$id,'name'=>$name,'slug'=>$key,'description'=>'Discover the '.strtolower($name).' collection.','image'=>$imgs[$key]??'','active'=>1,'sort_order'=>$i,'created_at'=>now(),'updated_at'=>now()]); }
    $samples=[['Stone Bead Bracelet','bracelets',2400,null],['Golden Layers Necklace','necklaces',3200,null],['The Everyday Ring Set','rings',2800,2400],['Sculpted Gold Earrings','earrings',3000,null]];
    foreach($samples as $i=>[$name,$cat,$price,$sale]) insert_record('products',['id'=>uuid(),'category_id'=>$ids[$cat],'name'=>$name,'slug'=>slug($name),'short_description'=>'A little detail with a lot of charm.','description'=>'Sample catalog piece for store setup. Replace this description, confirm materials and measurements, and upload your own product photography before opening the store.','price'=>$price,'sale_price'=>$sale,'images'=>json_encode([$imgs[$cat]]),'stock'=>12,'active'=>1,'featured'=>1,'sort_order'=>$i,'created_at'=>now(),'updated_at'=>now()]);
    $sections=[
        ['hero','Find your\neveryday charm.','Little things, beautifully chosen. Discover jewelry and accessories that feel like you.',$imgs['bracelets'],'Explore the collection','/shop'],
        ['categories','A charm for every mood','Discover your next little favorite.','','View all collections','/categories'],
        ['featured','The pieces you’ll reach for','Thoughtful details. Effortless style.','','Shop all pieces','/shop'],
        ['about','More than an accessory.\nA little piece of you.','We believe the smallest details can make the biggest difference. M’s Charm brings together accessories that add a little beauty to your everyday.',$imgs['rings'],'Our story','/about'],
        ['promotions','Something lovely, just for you','Discover our latest store announcements.','','',''],
        ['reviews','A little love from our community','Stories from the people who wear M’s Charm.','','Read & share reviews','/reviews'],
        ['social','Stay close to the charm','Follow along for new arrivals, styling inspiration, and little moments of beauty.','','',''],
        ['contact','Let’s find your next favorite','Have a question or need help choosing? We’re here for you.','','Get in touch','/contact'],
    ];
    foreach($sections as $i=>[$id,$title,$description,$image,$cta,$url]) insert_record('homepage_sections',['id'=>$id,'title'=>str_replace('\\n',"\n",$title),'description'=>$description,'image'=>$image,'cta_text'=>$cta,'cta_url'=>$url,'active'=>1,'sort_order'=>$i,'updated_at'=>now()]);
    $pages=[
        ['about','Our story','M’s Charm is a celebration of the little things. We curate accessories to add beauty and personality to the everyday.\n\nAccessories & More — pieces to make your own.'],
        ['faq','A little help','How do I place an order?\nChoose your pieces, add them to your bag, and enter your delivery details at checkout.\n\nHow can I get help?\nSend us a message through our contact page.'],
        ['privacy','Privacy Policy','STORE OWNER: Replace this starter policy with your reviewed policy before accepting real orders.\n\nWe collect the contact and delivery details you provide to fulfill your orders and respond to your enquiries. Account passwords are stored as secure hashes. Essential session cookies support sign-in and your shopping bag. We do not store payment card details.\n\nContact the store to request access to, correction of, or deletion of your information, subject to applicable record-keeping requirements.'],
        ['terms','Terms & Conditions','STORE OWNER: Replace these starter terms with your reviewed terms before accepting real orders.\n\nOrders are subject to stock availability and confirmation. Prices and delivery charges are displayed before submission. Payment is due on delivery. Contact us promptly if you need to change or cancel an order.'],
        ['delivery-returns','Delivery & Returns','STORE OWNER: Add your delivery areas, delivery timeframes, cancellation rules, and return/refund policy before accepting real orders.\n\nDelivery charges are shown at checkout. Please contact us with any questions about your order.'],
    ];
    foreach($pages as [$id,$title,$body]) insert_record('pages',['id'=>$id,'slug'=>$id,'title'=>$title,'body'=>str_replace('\\n',"\n",$body),'meta_title'=>$title,"meta_description"=>$title.' — M’s Charm','active'=>1,'updated_at'=>now()]);
    foreach([['Home','/','header'],['Shop','/shop','header'],['Collections','/categories','header'],['Our story','/about','header'],['Contact','/contact','header'],['Shop all','/shop','footer'],['Our story','/about','footer'],['Contact','/contact','footer'],['FAQ','/faq','footer'],['Delivery & returns','/delivery-returns','footer'],['Privacy policy','/privacy','footer'],['Terms & conditions','/terms','footer']] as $i=>[$label,$url,$location]) insert_record('navigation',['id'=>uuid(),'label'=>$label,'url'=>$url,'location'=>$location,'active'=>1,'sort_order'=>$i]);
}
