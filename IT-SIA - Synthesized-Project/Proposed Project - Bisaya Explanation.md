# Pagsabot sa Proposed Project

## Davao del Sur Blood Repository and Donor Monitoring System

**Status:** Proposal pa kini para sa pag-check ug pag-ayo sa mga kinahanglanon sa proyekto.

**Lugar sa unang paggamit:** Philippine Red Cross Davao del Sur Chapter

**Porma sa sistema:** Website nga magamit sa computer, tablet, ug cellphone browser.

**Gamiton nga teknolohiya:** Laravel ug MySQL.

## 1\. Unsa Kini nga Proyekto?

Ang proposed project kay usa ka website nga motabang sa pagtipig ug pagdumala sa impormasyon bahin sa dugo ug mga donor.

Ang tumong mao ang paghimo og usa ka lugar diin dali makita ang:

* Mga klase sa dugo nga anaa pa.
* Kadaghanon sa aktibong blood bags.
* Impormasyon sa mga donor.
* Kasaysayan sa ilang pag-donar.
* Kahimtang kung pwede pa ba sila makadonar.
* Dugo nga hapit na ma-expire.

Ang website makatabang sa Red Cross staff ug sa Barangay Health Coordinators sa ilang adlaw-adlaw nga trabaho. Ang publiko makakita lamang sa impormasyon bahin sa blood availability. Dili sila makakita sa pribadong impormasyon sa donor.

## 2\. Ngano Kinahanglan Kini?

Sa kasamtangan nga pamaagi, usahay moabot og **15-20 minutos** ang pagpangita kung unsang dugo ang anaa. Ang ubang impormasyon naa pa sa papel, busa lisod pangitaon ug usahay dili parehas ang record.

Kung adunay emergency, ang mga tawo usahay mosalig sa social media posts nga dili sigurado kung tinuod o bag-o pa ba ang impormasyon.

Pinaagi sa proposed system:

* Mas dali pangitaon ang impormasyon.
* Mas hapsay ang pag-update sa records.
* Mas dali makita ang dugo nga hapit na ma-expire.
* Mas kontrolado kung kinsa ang makausab sa records.
* Mas klaro sa publiko kung unsang dugo ang makita nga anaa.

## 3\. Pangunang Tumong

Ang pangunang tumong mao ang pagpa-dali, pagpa-husto, ug pagpa-sigurado sa pagdumala sa donor records ug blood inventory sa unang pilot area sa Davao del Sur.

## 4\. Kinsa ang Mogamit?

### Red Cross Administration

Sila ang mohatag og direksyon, pagtugot, budget, ug mga lagda sa paggamit sa sistema.

### Red Cross Personnel

Sila ang magbutang ug mag-update sa donor records, blood inventory, ug reports.

### Barangay Health Coordinators

Sila ang motabang sa pagparehistro sa donor, pagpangita og donors, ug pag-update sa impormasyon sa ilang lugar.

### Donors

Sila ang mohatag sa ilang husto nga impormasyon ug moapil sa donation activities.

### Blood Seekers

Sila ang motan-aw sa public locator aron mahibalo kung unsang blood type ang makita nga anaa.

### IT ug Development Team

Sila ang motukod, mag-atiman, ug motabang sa sistema kung adunay problema.

## 5\. Unsa ang Main Parts sa Sistema?

### A. Blood Inventory

Dinhi ibutang ang impormasyon sa blood type, blood bags, kadaghanon, petsa sa expiry, ug mga pag-usab sa stock.

Ang matag pagdugang, pagminus, o pag-correct sa stock kinahanglan adunay record kung kinsa ang nagbuhat, unsa ang rason, pila ang giusab, ug kanus-a kini gibuhat.

### B. Donor Records

Dinhi ibutang ang profile sa donor, donation history, ug impormasyon kung pwede pa ba siya makadonar o kinahanglan pa maghulat.

### C. Public Blood Availability Locator

Kini usa ka public page nga walay login. Makita dinhi ang current blood availability ug ang oras kung kanus-a katapos gi-update ang impormasyon.

Ang page dili magpakita sa ngalan o pribadong impormasyon sa donor. Ang makita nga blood availability dili reservation ug dili garantiya nga dayon ma-release ang dugo.

### D. User Access

Dili parehas ang access sa tanan. Ang authorized staff lamang ang makadugang o maka-usab sa donor ug inventory records.

Ang public users view-only ra. Makatan-aw sila, pero dili sila makausab sa impormasyon.

### E. Reports and Simple Data View

Makita sa staff ang mga report bahin sa stock, donor trends, kakulang sa dugo, ug blood bags nga hapit na ma-expire.

### F. Notifications and Alerts

Ang sistema mahimong magpadala og approved SMS reminders para sa donors, shortage notices, ug expiry alerts pinaagi sa approved SMS gateway.

### G. Backup and Recovery

Maghimo og backup sa records aron adunay kopya kung mawala o madaot ang main data. Kinahanglan usab adunay klaro nga paagi sa pagbalik sa data.

## 6\. Simple nga Daloy sa Sistema

1. Ang authorized staff ug Barangay Health Coordinators mo-login sa web application.
2. Ang sistema motan-aw kung unsa ilang gitugotan nga buhaton.
3. Ang staff magdumala sa donor records ug blood inventory.
4. Ang blood inventory mohatag sa current stock information ngadto sa public locator.
5. Ang donor records mahimong gamiton sa pagpadala og approved reminders.
6. Ang inventory mohatag og alert kung adunay shortage o blood bags nga hapit na ma-expire.
7. Ang reports motabang sa staff sa paghimo og mas maayong desisyon.
8. Ang backup ug audit records motabang sa pagpanalipod sa impormasyon.

## 7\. Unsa ang Apil sa Unang Phase?

Apil sa unang phase ang:

* Philippine Red Cross Davao del Sur Chapter.
* Piniling Barangay Health Coordinators.
* Responsive website nga magamit sa browser.
* Aktibong donor records.
* Initial blood inventory.
* Manual checking sa laboratory ug blood-safety information.
* Public page para sa view-only blood availability.

## 8\. Unsa ang Dili Pa Apil?

Dili pa apil sa unang phase ang:

* Pag-connect sa ubang Red Cross chapters o national registries.
* Pag-connect sa laboratory information system.
* Pag-connect sa blood analyzer o screening device.
* Online payment o financial transactions.
* Online blood reservation.
* Pag-usab sa public sa blood inventory.
* Native Android o iOS application.
* Pagbutang sa tanang karaan nga paper records sa sistema.

## 9\. Giunsa Pagpanalipod ang Records?

Ang pribadong impormasyon kinahanglan dili makita sa dili awtorisadong tawo. Ang sistema gamiton ang mosunod nga proteksyon:

* Login ug role-based access.
* Pag-check sa data una kini i-save.
* Record sa matag pag-usab sa inventory.
* Encryption kung mahimo ug sakto ang deployment setup.
* Regular backup.
* Paagi sa pagbalik sa data kung adunay problema.
* Accessible nga forms ug klaro nga error messages.

Ang eksaktong security settings kinahanglan pa ma-check sa testing ug sa actual hosting setup.



## 10\. Mga Posibleng Problema ug Solusyon

|Posibleng problema|Suhestiyon nga solusyon|
|-|-|
|Mahadlok o dili ganahan ang staff sa bag-ong sistema|Training, practice area, peer support, ug pagpaminaw sa feedback|
|Dili maanad ang uban sa paggamit og computer|Simple nga guides, tabang sa registration, ug coordinator support|
|Magpadayon ang paggamit sa papel|Klaro nga duha ka semana nga transition ug approved nga petsa sa pagtapos sa paper records|
|Hinay o mawala ang internet o kuryente|UPS, recovery procedures, ug klaro nga offline plan|
|Sayop ang stock information|Data checking, access control, history sa changes, backup, ug regular nga pag-compare sa records|
|Dili masabtan sa publiko ang blood availability|Ibutang ang last update time, view-only notice, ug no-reservation notice|

## 11\. Unsaon Pagkahibalo nga Nagmalampuson ang Proyekto?

Ang proyekto mahimong tan-awon nga nagmalampuson kung:

* Mas moubos ang pagsalig sa dili sigurado nga social media blood appeals.
* Mokunhod ang blood-product waste og **15%**.
* Mas dali ang blood availability search kaysa sa daang **15-20 minutos**.
* Kompleto ang digital records sa aktibong donors ug initial inventory.
* Nahuman sa training ang 100% sa core staff ug Barangay Health Coordinators.
* Labaw sa o parehas sa **90%** ang malampuson nga UAT workflows.
* Mahuman ang operational handover sulod sa **60 ka adlaw** human sa launch.

## 12\. Mga Butang nga Kinahanglan Pang Desisyunan

Dili pa final ang tanan. Kinahanglan pa tubagon sa team ug stakeholders ang mosunod:

1. Pila gyud ka functionality ang apil: siyam o napulo?
2. Unsa ka kanunay mag-backup sa data?
3. Aktibong donors ug initial inventory ra ba ang ibutang, o apilon ang mas daghang karaang records?
4. Davao del Sur ra ba ang unang lugar, o apilon ang ubang localities?
5. Unsaon paggamit sa sistema kung walay internet?
6. Unsa ka kanunay i-refresh ang public blood availability?
7. Unsa ang rules sa privacy, pagtipig sa records, audit, ug ownership?
8. Unsa nga Laravel ug PHP versions ang gamiton?

## 13\. Katapusan

Ang proposed system dili pa final nga approved system. Kini usa ka plano nga gamiton sa pag-check sa tinuod nga panginahanglan sa Red Cross, staff, Barangay Health Coordinators, donors, ug blood seekers.

Ang sunod nga lakang mao ang paghisgot sa requirements uban sa stakeholders. Human ma-approve ang mga desisyon, mahimo na ang final scope, database plan, testing plan, ug implementation plan.

**Pinasayon nga mensahe:** Mas dali pangitaon ang impormasyon, mas hapsay ang pagdumala sa blood records, ug mas klaro sa publiko ang blood availability.

