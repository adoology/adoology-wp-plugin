Delivered-To: mdamadulhaque8@gmail.com
Received: by 2002:a05:612c:2f09:b0:60b:e09c:ef5c with SMTP id kl9csp9293241vqb; Mon, 21 Sep 2026 08:32:25 -0700 (PDT)
X-Received: by 2002:a05:6102:5cc5:b0:79a:21f6:20fc with SMTP id ada2fe7eead31-7a55a524c55mr4577543137.10.1790004744651; Mon, 21 Sep 2026 08:32:24 -0700 (PDT)
ARC-Seal: i=1; a=rsa-sha256; t=1790004744; cv=none; d=google.com; s=arc-20260327; b=fUFF1Swntq+j8s0l5ag/IQQSFyBXCgE55TK3Tmpr2KM7ylUOPcNU9mR83+gMcnCIY6 Ha9qz3Rg1856HMFEZlx5GS+lMOiFQa7A3t+Up96pPDyyDi8Vm/BuIGjSovPQndVeJuWL HLiq7q8Tu5IWlws+jw5eIHikk20vbbWDsIFwr459OidV5pfUJLjHsSCcVkYKDUETqiOd GtBQTA0XrrG5m8APx4jwCkIXmfBx0Ps9MXt9gcdq8XBTyNqRsliN7atU2b+JyAQcUyVI R90d7th+u8NMI946Eka/PhctdQteKjAGaRCE83nnNhdE1VQOQ7JwviEI1NOECv4my70p BRqw==
ARC-Message-Signature: i=1; a=rsa-sha256; c=relaxed/relaxed; d=google.com; s=arc-20260327; h=mime-version:date:message-id:cfbl-feedback-id:cfbl-address:subject :to:from:dkim-signature:dkim-signature; bh=2Y09jzgX6v7GOTyzzeYwzojA2slTqSnFky4BymmiovE=; fh=mRLtTyzZ3O/to8Zig8YyADW4vCmAEx5aVq/mFs/39KM=; b=JvoN2pMeouHpmBb2rq6tJzjTF0asETt/XT4i50bCfFx5X9YUiiiAlr95bjJHCZn0ZC 5G2k4a2lqjWx8bp/QqscXIJZMRWPrkwAXzVoBr8rmViQZPMEXKCMkMjr0jgeqIH/04Lv Qy+dT4XSmGBzaMbb1jRa3Kwzsi266G3IVjo376kNddOmm9bBoCxJ/VoWjVowbG4NgUlg oWJPrVp5ng8CYRykBLWRcKxHJywkWtNDNLWbBGnZFJFwf6mna4yjTcxguuPGiuNuvp9j qHma25Kga6OBx8xxk2T1eHZgKgqObJ5B+SdzlKl6a1OYZvyL4N1OZnI+H38r+qq02EpF m0jA==; dara=google.com
ARC-Authentication-Results: i=1; mx.google.com; dkim=pass header.i=@wordpress.org header.s=strong2 header.b=SvfOIeoL; dkim=pass header.i=@helpscout.net header.s=strong2 header.b=Md1q8dS+; spf=pass (google.com: domain of l195xvpwn8.2pqkjpj7k8+v1@bounce.helpscout.net designates 34.198.122.65 as permitted sender) smtp.mailfrom=l195xvpwn8.2pqkjpj7k8+v1@bounce.helpscout.net; dmarc=pass (p=REJECT sp=REJECT dis=NONE) header.from=wordpress.org
Return-Path: <l195xvpwn8.2pqkjpj7k8+v1@bounce.helpscout.net>
Received: from mail2.helpscout.net (mail2.helpscout.net. [34.198.122.65]) by mx.google.com with ESMTPS id 6a1803df08f44-91260c1ddc4si97736626d6.219.2026.09.21.08.32.24 for <mdamadulhaque8@gmail.com> (version=TLS1_3 cipher=TLS_AES_256_GCM_SHA384 bits=256/256); Mon, 21 Sep 2026 08:32:24 -0700 (PDT)
Received-SPF: pass (google.com: domain of l195xvpwn8.2pqkjpj7k8+v1@bounce.helpscout.net designates 34.198.122.65 as permitted sender) client-ip=34.198.122.65;
Authentication-Results: mx.google.com; dkim=pass header.i=@wordpress.org header.s=strong2 header.b=SvfOIeoL; dkim=pass header.i=@helpscout.net header.s=strong2 header.b=Md1q8dS+; spf=pass (google.com: domain of l195xvpwn8.2pqkjpj7k8+v1@bounce.helpscout.net designates 34.198.122.65 as permitted sender) smtp.mailfrom=l195xvpwn8.2pqkjpj7k8+v1@bounce.helpscout.net; dmarc=pass (p=REJECT sp=REJECT dis=NONE) header.from=wordpress.org
DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; s=strong2; d=wordpress.org; h=From:To:Subject:X-Msg-Context:CFBL-Address:CFBL-Feedback-ID:Message-Id:Date: Content-Type:MIME-Version:from:to:subject:reply-to:cc; i=plugins@wordpress.org; bh=2Y09jzgX6v7GOTyzzeYwzojA2slTqSnFky4BymmiovE=; b=SvfOIeoLVomQqnQbN5BWc5YYCN1i0LIaRMHvrZGLjpyAzUFmeooa5h0vDynEUr1KlqgPFd/aSAB+ LnutIlvQ69W8dkuBMeMEKNpNSxSCgkH/Q/5qqimZorGFYYQrwBsSjmYTfNv9Bqqy3vdw94PcRPHC eYlUpeoGzgYHK9KrdyGkBOcBpK09yS2u7C0gKDW1xPP/ZldO6p7NMTfvI/RB/WTXe1Lhywf6PcIt MV843bVLkDBHmOQJcUJkntQcoXOUzt91Tkd4N0PaPr65iescPpQu6bpeuPP9tkypvDV02F33xPb6 2dOtt9+hOZz3lOZy1x3vokN2HYl5Mz7DQ2bUdg==
DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed; s=strong2; d=helpscout.net; h=From:To:Subject:X-Msg-Context:CFBL-Address:CFBL-Feedback-ID:Message-Id:Date: Content-Type:MIME-Version:from:to:subject:reply-to:cc; bh=2Y09jzgX6v7GOTyzzeYwzojA2slTqSnFky4BymmiovE=; b=Md1q8dS+9uwJYbla7EGkkcRdA+WYYBTn2lT4EKIS5djhN5sKNoQ9Dp3XOKz6P6Z1DtCUHhcIfidj fGDMsgBKZwm1i9/I9tI7I7jR6X1IpdwThw+1P4Bdlef5Atf6v1tTjDQwce9bAByOQ5rq6qJSKVe1 XBtIDvWDkhYD22uaKvMm3oJDQPmRwTYnPAgYEegl8gLDOFHsnTEnrEj1GV22FyJ8+LyAXe8xg57l Lta7DQ6cf3OGD70NAo/MoN2sCZpDC7BuL3q0UPIx+yMxdMhstnviF6eGquyxPjVJ8gSTkVqiLsUL kCOwiKWGJpuqk3U8LAHfjZLFi00dMCJyI3IGaQ==
Received: by mail2.helpscout.net id hm570g3bd7g7 for <mdamadulhaque8@gmail.com>; Mon, 21 Sep 2026 15:31:31 +0000 (envelope-from <l195xvpwn8.2pqkjpj7k8+v1@bounce.helpscout.net>)
From: WordPress.org Plugin Directory <plugins@wordpress.org>
To: amadulhaque8 <mdamadulhaque8@gmail.com>
Subject: [WordPress Plugin Directory] Review in Progress: Adoology for WooCommerce
X-Hs-Format: 2
X-Msg-Context: HS-1-168033-3457661406-10557854393
CFBL-Address: fbl@helpscout.net; report=arf
CFBL-Feedback-ID: HS-1-168033-3457661406-10557854393
X-Hs-Cid: 83653
X-Hs-Uid: 3Zg7QbE5zYBjqmMRV6ev
X-Report-Abuse: Please forward a copy of this message, including all headers, to abuse@helpscout.net
X-Hs-State: 3
Message-Id: <reply-83653-3457661406-10557854393-1790004690-1110549903@helpscout.net>
X-Msg-Timestamp: 2026-09-21T15:31:31+00:00
Date: Mon, 21 Sep 2026 15:31:31 +0000
Content-Type: multipart/alternative; boundary="=_149b49720702d36b8c90baac34f0984f"
MIME-Version: 1.0

--=_149b49720702d36b8c90baac34f0984f
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

-- Please reply above this line --


            =F0=9F=93=A6 This is a review of the file adoology-connector-0.=
1.7.zip
submitted 7 hours ago. Test it on Playground.=F0=9F=91=8B amadulhaque8 -
Let=E2=80=99s improve your plugin!

Thank you for submitting your plugin,  "Adoology for WooCommerce".

Our volunteer reviewers, tools, and/or AI aids identified issues in
your plugin that require your attention.

We=E2=80=99ve pended your submission to give you a chance to review and fix
these common issues.

=F0=9F=A4=96  Please note that this message was generated using a combinati=
on
of humans, algorithms, and AI in varying proportions. It may not have
been reviewed by a human. All AI outputs are marked with the =E2=9C=A8
emoji. Pay attention to it, it's quite accurate.

Who are we?

The WordPress.org Plugins Directory is a service that the WordPress
community offers to the world: free hosting, distribution and updates
for more than 65,000 plugins, available to every WordPress site at no
cost. This is made possible by the WordPress Foundation, the donated
infrastructure that supports WordPress.org, and the volunteers who
keep it running.

Being listed in the directory is not a commercial service and should
not be taken for granted; it is an invitation to be part of this
community, and it comes with the shared responsibility of keeping the
directory safe and reliable for the millions of users who trust it.

We, the Plugins Team, are a group of volunteers who help you identify
common issues so that you can make your plugin more secure,
compatible, reliable and compliant with the guidelines. For
consistency and better communication, your review is assigned to a
single volunteer who will assist you throughout the entire process.

The review process

        1. Read this email carefully from start to finish. Review
every issue, including the linked documentation and the provided
examples. Then search your codebase for other occurrences of the same
issues, even if they are not explicitly mentioned in this review. Take
the time to understand each issue so you can apply what you learn to
your plugin going forward.

        2. Address all identified issues, test your plugin thoroughly
and upload a corrected version. If you have doubts about a specific
issue, fix everything else and ask your questions alongside the
update.Note: The Plugins Team volunteers are not your developers or QA
team. They are here to help you identify and understand issues so that
you can improve and maintain your plugin in the future. Finding and
fixing these issues remains your responsibility.

        3. Reply to this email thread (do not create a new email or
reply to the submission confirmation). Your plugin will then be added
to your reviewer's queue, and they will send you any remaining issues
that are identified.

        4. Once no further issues remain, your plugin will be approved
=F0=9F=8E=89Fewer review cycles mean quicker approval, while multiple round=
s
can extend the process to weeks or months.

Before you reply

            Throughout the entire process, and before you reply,
please ensure that:

                You have addressed all reported issues and thoroughly
tested your plugin. Make sure your updated version does not introduce
new issues, such as fatal errors during activation.
                You are replying to this email thread.
                Your reply is brief and to the point. Include only
information that is relevant for the next review; there is no need to
describe every change you made, and please avoid unnecessary verbosity
or AI-generated filler.
                If you wish to alter your permalink (aka the plugin
slug)  "adoology-for-woocommerce", you have explicitly stated your
desired permalink in your reply. Changing the display name alone is
not sufficient, and permalinks cannot be altered after approval.
                You are making meaningful progress between review
rounds. Updates that resolve only a small portion of the reported
issues delay the review process, and the plugin may be rejected out of
respect for the volunteers' time and other plugin authors waiting in
the queue. Plugins rejected under these circumstances will not be
reviewed again.
                You treat the review as a learning opportunity and
improve your knowledge of development practices and the directory
guidelines accordingly.

            Each volunteer may review hundreds of plugins every week.
Following these indications and submitting a well-tested update makes
the review process smoother for everyone.

Response times

Reviews are handled by volunteers alongside their own personal and job
commitments, so response times depend entirely on their availability.
You may receive a reply within a few days, after a week or two, or
occasionally after a longer period. We appreciate your patience and
understanding.

Please avoid requesting status updates unless you have been waiting an
unusually long time (a month or more). Status requests do not
accelerate the review; they slow it down by taking time away from the
volunteers working through the queue. If you have replied to this
email thread, you are in the queue. If you believe a reply went
missing, check your spam folder and other inboxes first; if you have
lost this email, write to us from the same address we sent it to and
request that it be resent.

Guidelines

In addition to code quality, security and functionality, all plugins
must adhere to the guidelines you accepted when submitting this
plugin. Please keep them in mind when making changes:
https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guide=
lines/.

Please note that failure to follow the Plugins Directory guidelines is
taken seriously and may result in the loss of your WordPress.org
plugin hosting privileges.

Have you read the guidelines and this plugin complies with them?

Upon submitting your plugin, you agreed and confirmed that it complies
with the WordPress.org Plugin Directory Guidelines, which apply to all
plugins in the directory.

Our automated tools have detected patterns that may require a closer
look regarding compliance with certain guidelines. We will verify this
during our manual review, but it=E2=80=99s best to address any potential
issues beforehand. In particular, please pay attention to the
following:
Plugins should not hijack the admin dashboard. Upgrade prompts,
notices, alerts, and the like must be limited in scope and used with
moderation. (Guideline 11)

Please check it, and if you think everything is fine, do not worry.
Our tools are very thorough and may highlight different things as
potential issues.Are you the rightful owner of this plugin?

We know this might seem like an odd question =E2=80=94 especially coming
from an algorithm =E2=80=94 but bear with us; it=E2=80=99ll all make sense =
soon.

Here's how it works: If your plugin uses a name or URL associated with
a specific entity, we need to confirm that you actually represent that
entity. It's that simple.

So, how do we verify your identity? In most cases, we rely on the
domain in your email address.

This situation usually comes up if you're using a personal account to
submit a plugin meant to represent a company or if you=E2=80=99re a third
party attempting to upload a plugin on someone else=E2=80=99s behalf (pleas=
e
don=E2=80=99t do that).

This is what we know about this plugin:
Plugin name: "Adoology for WooCommerce" in the readme.txt filePlugin
name: "Adoology for WooCommerce" in the adoology-connector.php
fileSlug: adoology-for-woocommerceAuthor: AdoologyPlugin URI:
https://adoology.comContributors:adoology

This is what we know about you:
Username: amadulhaque8=F0=9F=9F=A7 Your username does not match any of the
contributors declared in the plugin.Email:
mdamadulhaque8@gmail.com=F0=9F=9F=A5 Your email domain "gmail.com" does not
seem to be related to any of the URLs, names, trademarks and/or or
services declared in the plugin.=F0=9F=9F=A5 A gmail.com account cannot be
used as a valid form of identification towards clarifying ownership,
even if it contains your or your company's name. Anyone can create any
gmail.com account that is not already in use. You could be you, but
you could also be anyone else.

Please note that we carry out these checks to protect people like you.
If someone else submitted a plugin under your name or your company's
name, you would rightly be upset if we failed to confirm ownership in
a demonstrably reliable way.

=E2=98=91=EF=B8=8F You can demonstrate or clarify ownership in one of the
following ways:

    =F0=9F=8C=90 Perform a DNS check on the owner's domain. Add a TXT recor=
d
at the owner's domain root @ with the following value:
wordpressorg-amadulhaque8-verificationBefore replying, verify that the
record is already visible at the relevant domain, for example:
adoology.com (DNS changes can take some time to propagate).

        Note: This will only be considered valid if that's done in the
owner's domain and we can relate your account as being controlled by
the owner. If you are a third party please follow other verification
methods.

    =F0=9F=93=A9 Update your WordPress.org email address to one under the
domain of the entity associated with the plugin. You can change it in
Your WordPress.org profile, we cannot change it for you.

        Note: We will continue the review in this email thread. Any
new emails will be sent to the new email account.

    =F0=9F=91=A4 Reply asking us to transfer this submission to the correct
WordPress.org account. Please, tell us the username. If the owner does
not yet have an account, they can create it using an email that is
under the domain of the entity associated to the plugin.

        =E2=9A=A0=EF=B8=8F Do not resubmit this plugin using the new accoun=
t!. I
will transfer it for you. Simply reply to this email with the new
username.

        Note: Once the plugin has been approved, you can ask the owner
to add you as a committer in the "Advanced" section of the plugin, so
that you can commit code using your account.

    =F0=9F=9B=A0 Change the plugin's display name and slug to make it clear
that the plugin is not officially affiliated with any other entity.
Upload an updated version of your plugin via the "Add your plugin"
page. Ask us to change the slug.
    Reply to this email clarifying the situation, we know that
sometimes you might be using an email account that belongs to the same
entity or an entity that owns the other entity. Also, if you already
have established plugins in the directory under the same account we
can use that as tacit verification.

Remember that, if you own other plugins, all the plugins belonging to
the same entity should be under the same WordPress.org account.

=E2=9A=A0=EF=B8=8F Please do not resubmit this plugin using a different acc=
ount.
Both submissions will be rejected if you do so, and your accounts may
be suspended until the situation is resolved. Instead, ask us to
change the owner =E2=80=94 we can certainly do that.
Have you checked for common technical issues?

Please ensure that your plugin adheres to the guidelines and best
practices, including the following:

=F0=9F=94=B4 Use wp_enqueue commands

=E2=84=B9=EF=B8=8F Why it matters: Because of performance and compatibility=
,
please make use of the built in functions for including static and
dynamic JS and/or CSS.

=F0=9F=94=8D Identify JS and CSS outputs: Look for any <script> or
<style> HTML tags in your plugin. In the majority of cases you
could enqueue them.

=F0=9F=9B=A0 Fix it: Make use of the specific function for enqueue them:

=09=09Type of code
=09=09Functions

=09=09Static JS
=09=09wp_register_script(), wp_enqueue_script(), admin_enqueue_scripts()

=09=09Inline JS
=09=09wp_add_inline_script()

=09=09Static CSS
=09=09wp_register_style(), wp_enqueue_style()

=09=09Inline CSS
=09=09wp_add_inline_style()

=F0=9F=91=89 In the public pages you can enqueue them using the hook
wp_enqueue_scripts().
=F0=9F=91=89 In the admin pages you can enqueue them using the hook=20
admin_enqueue_scripts(). You can also use admin_print_scripts() and
admin_print_styles().
=F0=9F=91=89 As of WordPress 6.3, you can easily pass attributes like defer=
 or
async, as of WordPress 5.7, you can pass other attributes by using
functions and filters.

Example:
function adoofowo_enqueue_script() {
    wp_enqueue_script( 'adoofowo_js', plugins_url( 'inc/main.js',
__FILE__ ), array(), ADOOFOWO_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'adoofowo_enqueue_script' );
Your JS/CSS is now enqueued!

Possible cases from your plugin include:
src/OrderForm.php:431 <style>src/Settings.php:708=20
...?><style>.adoology-cards{display:grid;grid-template-columns:repeat(auto-=
fit,minmax(190px,1fr));gap:16px;max-width:1100px;margin:20px
0}.adoology-card{padding:18px;border:1px solid #dcdcde;border-radiu...

=F0=9F=94=B4 Nonces and User Permissions Before Processing Requests

=E2=84=B9=EF=B8=8F Why it matters: Nonces and permissions checks ensure tha=
t the
request comes from a trusted source, protecting against security
threats like CSRF (Cross-Site Request Forgery) attacks.

Please check the official WordPress docs on nonces and this article
for details =E2=80=94 this is a quick summary.

=F0=9F=94=8D Spot it: Look for functions that interact with $_GET, $_POST,
$_REQUEST and perform actions triggered by the user/browser that
modify data or perform sensitive actions (e.g., privileged actions or
data manipulation). For such actions, you should always check for a
nonce.

Additionally, verify user permissions if the action is restricted to
certain roles (e.g., admin, editor).

When in doubt, play it safe: always check.

=F0=9F=9B=A0 Fix it

    Create a nonce ( wp_nonce_field(), wp_nonce_url(),
wp_create_nonce() ). If you need to pass the nonce to JavaScript you
can make use of wp_localize_script()
    Pass it with the request.
    Check the nonce ( check_admin_referer(), check_ajax_referer(),
wp_verify_nonce() ) , and permissions if applicable (
current_user_can() ) .

Example (nonce check):

function adoofowo_save_email(){
    if ( !isset( $_POST['adoofowo_nonce'] ) || !wp_verify_nonce(
sanitize_text_field( wp_unslash( $_POST['adoofowo_nonce'] ) ),
'adoofowo_save_email_action' ) ) {
        wp_send_json_error( 'Invalid nonce.' );
    }

    if ( !current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions.' );
    }

    if ( isset( $_POST['post_id'] ) && isset( $_POST['price'] ) ) {
        update_post_meta( absint( $_POST['post_id'] ), 'price',
floatval( $_POST['price'] ) );
    }
}
add_action( 'wp_ajax_adoofowo_save_email', 'adoofowo_save_email' );

=E2=9A=A0=EF=B8=8F Without nonce and permissions checks, anyone could call =
this
AJAX endpoint and change the price data for any post - risky!
Other details

We've detected some other details that you may want to check.=F0=9F=9F=A1
Requires Plugins: found possible dependencies
The "Requires Plugins" header is a comma-separated list of
WordPress.org-formatted slugs for its dependencies.

Does your plugin extend another plugin available in the WordPress.org
directory? If so, you can use the "Requires Plugins" header to have
WordPress check if that plugin is installed and activated before your
plugin is activated.

You can check out the documentation at:
https://make.wordpress.org/core/2024/03/05/introducing-plugin-dependencies-=
in-wordpress-6-5/

We have found that your plugin probably extends another plugin, so
using this header may be useful for you, please check it out.

Analysis result:
By checking your plugin slug "adoology-for-woocommerce" we found
coincidences with "woocommerce"You can easily solve this by adding or
updating the "Requires Plugins" header at "adoology-connector.php",
like so:Requires Plugins: woocommerce=F0=9F=94=B4=EF=B8=8F Tested Up To Val=
ue is Out
of Date, Invalid, or Missing
The tested up to value in your plugin is not set to the current
version of WordPress. This means your plugin will not show up in
searches, as we require plugins to be compatible and documented as
tested up to the most recent version of WordPress.

Please update your readme to show that it is tested up to the most
recent stable major version of WordPress. For example if WordPress
said that version 99.3.2 was the current release, you would be able to
set your value to 99.3 as that's the major release.

The following links will assist you in understanding WordPress's
versioning and the latest version:

https://wordpress.org/download/

https://make.wordpress.org/core/handbook/about/release-cycle/version-number=
ing/

You cannot set it beyond the current version, as that will cause your
plugin not to be available on searches. Also remember that WordPress
has made late changes in releases, so claiming compatibility with an
incomplete version is likely to land you in trouble.

From your readme file:
ERROR: Tested up to: "6.8" that version is bellow currently accepted
WordPress versions (It's expected to be at least "7.0").=F0=9F=94=B4=EF=B8=
=8F You
haven't added yourself to the "Contributors" list for this plugin.
In your readme file, the "Contributors" parameter is a case-sensitive,
comma-separated list of all WordPress.org usernames that have
contributed to the code.

Your username is not in this list, you need to add yourself if you
want to appear listed as a contributor to this plugin.

If you don't want to appear that's fine, this is not mandatory, we're
informing you just in case.

Analysis result:
# WARNING: None of the listed contributors "adoology" is the
WordPress.org username of the owner of the plugin
"amadulhaque8".=F0=9F=94=B4=EF=B8=8F Using composer but could not find comp=
oser.json
file
We noticed that your plugin is using Composer to handle library
dependencies, that's great as it will help maintaining and updating
your plugin in the future while avoiding collisions with other plugins
that are using same libraries.

The composer.json file describes the dependencies of your project and
may contain other metadata as well. It's a small file that typically
can be found in the top-most directory of your plugin.

As one of the strengths of open source is the ability to review,
observe, and adapt code, we would like to ask you to include that file
in your plugin, even if it is only used for development purposes. This
will allow others to exercise the open source freedoms from which we
all benefit.

From your plugin:

composer.json file not found in
"adoology-connector/composer.json"=F0=9F=94=B4=EF=B8=8F Undocumented use of=
 a 3rd
Party / external service
Plugins are permitted to require the use of third party/external
services as long as they are clearly documented.

When your plugin reach out to external services, you must disclose it.
This is true even if you are the one providing that service.

You are required to document it in a clear and plain language, so
users are aware of: what data is sent, why, where and under which
conditions.

To do this, you must update your readme file to  clearly explain that
your plugin relies on third party/external services, and include at
least the following information for each third party/external service
that this plugin uses:

    What the service is and what it is used for.
    What data is sent and when.
    Provide links to the service's terms of service and privacy
policy.

Remember, this is for your own legal protection. Use of services must
be upfront and well documented. This allows users to ensure that any
legal issues with data transmissions are covered.

Example:
=3D=3D External services =3D=3D
This plugin connects to an API to obtain weather information, it's
needed to show the weather information and forecasts in the included
widget.
It sends the user's location every time the widget is loaded (If the
location isn't available and/or the user hasn't given their consent,
it displays a configurable default location).
This service is provided by "PRT Weather INC": terms of use, privacy
policy.

=F0=9F=94=97 Please verify that the terms and privacy links exist and they
have the proper content. We will check those links in the next review.

Example(s) from your plugin:
src/Webhooks.php:349
wp_safe_remote_request($webhook->get_delivery_url(), ['method'
=3D> 'POST', 'timeout' =3D> 20, 'redirection' =3D> 0,
'reject_unsafe_urls' =3D> true, 'httpversion' =3D> '1.1',
'blocking' =3D> true, 'user-agent' =3D> sprintf('WooCommerce/%s
Hookshot (Adoology retry)', defined('WC_VERSION') ? WC_VERSION : ''),
'body' =3D> $body, 'headers' =3D> ['Content-Type' =3D>
'application/json', 'X-WC-Webhook-Source' =3D> home_url('/'),
'X-WC-Webhook-Topic' =3D> $topic, 'X-WC-Webhook-Resource' =3D>
$topic_bits[0] ?? 'product', 'X-WC-Webhook-Event' =3D>
$topic_bits[1] ?? 'updated', 'X-WC-Webhook-Signature' =3D>
base64_encode(hash_hmac('sha256', $body,
wp_specialchars_decode($secret, ENT_QUOTES), true)), 'X-WC-Webhook-ID'
=3D> $webhook_id, 'X-WC-Webhook-Delivery-ID' =3D>
$webhook->get_new_delivery_id(), 'X-Adoology-Event-ID' =3D>
$event_id]]);# =E2=9C=A8 The plugin sends webhook retry requests to
Adoology, but the readme lacks a Terms of Service or Privacy Policy
link for Adoology.src/Settings.php:357 ...'adoology_api_base_url',
'https://api.adoology.com')); ?>" required
/></td>...src/Settings.php:128 return (string)
Options::get('adoology_api_base_url',
'https://api.adoology.com');src/Settings.php:78 'default' =3D>
'https://api.adoology.com',src/Options.php:64 'adoology_api_base_url'
=3D> 'https://api.adoology.com',src/ApiClient.php:26 $url =3D
self::validate_base_url((string) Options::get('adoology_api_base_url',
'https://api.adoology.com'));src/Settings.php:130 $current =3D (string)
Options::get('adoology_api_base_url',
'https://api.adoology.com');=F0=9F=94=B4=EF=B8=8F Sanitization for
register_setting()
Fields registered through register_setting() should be sanitized
properly.

Fortunately, in this case, it is very easy to indicate which function
should be used to sanitize the input. =F0=9F=A7=BC

The third argument of this function accepts an array() in which you
can add your sanitizing function in the sanitize_callback key.

Just like this:

register_setting(
    'adoofowo_group',
    'adoofowo_option_name',
    array(
        'type'              =3D> 'string',
        'sanitize_callback' =3D> 'sanitize_text_field',
    )
);

Make sure you use a proper sanitization function (WordPress has plenty
of them!) and add other options as needed.

Please, check out the register_setting() documentation for more
information and code examples.

Example(s) from your plugin:

src/Settings.php:109 register_setting('adoology_features', $option,
['type' =3D> 'integer', 'sanitize_callback' =3D> [$this,
$sanitizer]]);=F0=9F=94=B4=EF=B8=8F Internationalization: Text domain does =
not match
plugin slug.
In order to make a string translatable in your plugin you are using a
set of special functions. These functions collectively are known as
"gettext".

These functions have a parameter called "text domain", which is a
unique identifier for retrieving translated strings.

This "text domain" must be the same as your plugin slug so that the
plugin can be translated by the community using the tools provided by
the directory.
As for example, if this plugin slug is "adoology-for-woocommerce" the
Internationalization functions should look like:

esc_html__( 'Hello', 'adoology-for-woocommerce' );

From your plugin, you have set your text domain as follows:
# This plugin is using the domain "adoology-connector" for 256
element(s).However, the current plugin slug is
this:adoology-for-woocommerce

=F0=9F=91=89 Your next steps

This is your checklist:
Have you read the guidelines and this plugin complies with them?Are
you the rightful owner of this plugin?Have you checked for common
technical issues?Other details

If there is something that needs to be fixed, please:

    Take your time and fix it. Make sure that everything was addressed
and the plugin works.
    Update your plugin files at the "Add your plugin" page, while
being logged in with your account  "amadulhaque8".
    Reply to this email.
        Please keep your reply short, direct and clear. Avoid overly
verbose and long AI responses. Do not list the changes made, we don't
need that, we will review the entire plugin again, we won't compare
the changes. However, please share any important context or
clarifications that may help us during the review.

If after checking the list and do the changes you feel that everything
is right or need further clarification, please reply to this email and
a volunteer will help you.

If you believe there is a requirement you cannot accomplish and choose
not to make changes, your plugin submission will be rejected after
three months.
Thanks!

By taking these steps, you're helping the Plugin Review Team work more
efficiently =E2=80=94 meaning your plugin (along with the thousands of
others in the queue) can be reviewed faster. =F0=9F=9A=80 We really appreci=
ate
your contribution!
Disclaimers

If, at any time during the review process, you wish to change your
permalink (aka the plugin slug) "adoology-for-woocommerce", you must
explicitly and clearly tell us what you would like it to be. Just
changing it in your code and in the display name is not sufficient.
Remember, permalinks cannot be altered after approval.

This email was partially auto-generated, so please be aware that some
information might not be entirely accurate. No personal data was
shared with the AI during this process. If you notice any obvious
errors or something seems off, feel free to reply =E2=80=94 we=E2=80=99ll b=
e happy
to take a closer look and readjust this automation.

Review ID: AUTOPREREVIEW =E2=9D=97OWN
adoology-for-woocommerce/amadulhaque8/21Sep26/T1 21Sep26/4.3
(P0TDX373201HGN)
--
WordPress Plugins Team |=C2=A0plugins@wordpress.org
https://make.wordpress.org/plugins/=20
https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guide=
lines/
https://wordpress.org/plugins/plugin-check/
                   =20



--=_149b49720702d36b8c90baac34f0984f
Content-Type: text/html; charset=UTF-8
Content-Transfer-Encoding: quoted-printable

<html lang=3D"en">
<head>
    <meta content=3D"text/html; charset=3Dutf-8" http-equiv=3D"Content-Type=
">
</head>
<body>
<div id=3D"hsReplyAbove" class=3D"hsReplyAbove">
    <div class=3D"kickstarter-compatible" style=3D"font-family:sans-serif;"=
>
       =20
                        <div>
   =20
   =20

=F0=9F=93=A6 This is a review of the file <strong>adoology-connector-0.1.7.=
zip</strong> submitted <strong>7 hours ago</strong>. Test it on <a href=3D"=
https://playground.wordpress.net/?blueprint-url=3Dhttps%3A%2F%2Fwordpress.o=
rg%2Fplugins%2Fwp-json%2Fplugins%2Fv1%2Fplugin%2Fadoology-for-woocommerce%2=
Fblueprint.json%3Fzip_hash%3Dcc26774df9998cddf2466c43ed55ac43%26type%3Dpcp"=
>Playground</a>.<br><br><h2>=F0=9F=91=8B amadulhaque8 - Let=E2=80=99s impro=
ve your plugin!</h2>
<br>
Thank you for submitting your plugin,  "Adoology for WooCommerce".
<br><br>
Our volunteer reviewers, tools, and/or AI aids identified <strong>issues in=
 your plugin that require your attention</strong>.
<br><br>
We=E2=80=99ve <strong>pended</strong> your submission to give you a chance =
to review and fix these common issues.
<br><br>
=F0=9F=A4=96 <i> Please note that this message was generated using a combin=
ation of humans, algorithms, and AI in varying proportions. It may not have=
 been reviewed by a human. All AI outputs are marked with the =E2=9C=A8 emo=
ji. Pay attention to it, it's quite accurate.</i>
<br><br>
<h3>Who are we?</h3>
<br>
The WordPress.org Plugins Directory is a service that the WordPress communi=
ty offers to the world: <strong>free hosting, distribution and updates for =
more than 65,000 plugins</strong>, available to every WordPress site at no =
cost. This is made possible by the WordPress Foundation, the donated infras=
tructure that supports WordPress.org, and the volunteers who keep it runnin=
g.
<br><br>
Being listed in the directory is not a commercial service and should not be=
 taken for granted; it is <strong>an invitation to be part of this communit=
y</strong>, and it comes with the shared responsibility of keeping the dire=
ctory safe and reliable for the millions of users who trust it.
<br><br>
We, the Plugins Team, are a group of volunteers who <strong>help you identi=
fy common issues</strong> so that <strong>you can make your plugin more sec=
ure, compatible, reliable and compliant</strong> with the guidelines. For c=
onsistency and better communication, your review is assigned to a single vo=
lunteer who will assist you throughout the entire process.
<br><br>
<h3>The review process</h3>
<br>
<table border=3D"0" style=3D"border:none!important;">
    <tbody>
    <tr>
        <td style=3D"min-width: 60px; max-width: 150px; padding:20px;"><img=
 style=3D"width: auto; max-width: 110px;" alt=3D"An email envelope." src=3D=
"https://make.wordpress.org/plugins/files/2025/07/manual-review-2.png"></td=
>
        <td><strong>1. Read this email carefully from start to finish.</str=
ong> Review every issue, including the linked documentation and the provide=
d examples. Then search your codebase for other occurrences of the same iss=
ues, even if they are not explicitly mentioned in this review. Take the tim=
e to understand each issue so you can apply what you learn to your plugin g=
oing forward.</td>
    </tr>
    <tr>
        <td style=3D"min-width: 60px; max-width: 150px; padding:20px;"><img=
 style=3D"width: auto; max-width: 110px;" alt=3D"A plugin author fixing the=
 issues." src=3D"https://make.wordpress.org/plugins/files/2025/07/manual-re=
view-3.png"></td>
        <td><strong>2. Address all identified issues, test your plugin thor=
oughly and upload a corrected version.</strong> If you have doubts about a =
specific issue, fix everything else and ask your questions alongside the up=
date.<br><br><i>Note: The Plugins Team volunteers are not your developers o=
r QA team. They are here to help you identify and understand issues so that=
 you can improve and maintain your plugin in the future. Finding and fixing=
 these issues remains your responsibility.</i></td>
    </tr>
    <tr>
        <td style=3D"min-width: 60px; max-width: 150px; padding:20px;"><img=
 style=3D"width: auto; max-width: 110px;" alt=3D"A volunteer reviewing the =
plugin." src=3D"https://make.wordpress.org/plugins/files/2025/07/manual-rev=
iew-1.png"></td>
        <td><strong>3. Reply to this email thread</strong> (do not create a=
 new email or reply to the submission confirmation). Your plugin will then =
be added to your reviewer's queue, and they will send you any remaining iss=
ues that are identified.</td>
    </tr>
    <tr>
        <td style=3D"min-width: 60px; max-width: 150px; padding:20px;"><img=
 style=3D"width: auto; max-width: 110px;" alt=3D"A new review of the plugin=
." src=3D"https://make.wordpress.org/plugins/files/2025/07/manual-review-4.=
png"></td>
        <td><strong>4. Once no further issues remain, your plugin will be a=
pproved</strong> =F0=9F=8E=89<br><br>Fewer review cycles mean quicker appro=
val, while multiple rounds can extend the process to weeks or months.</td>
    </tr>
    </tbody>
</table>
<br>
<h3>Before you reply</h3>
<br>
<table border=3D"0" style=3D"border:none!important;">
    <tbody>
    <tr>
        <td style=3D"min-width: 60px; max-width: 150px; padding:20px;"><img=
 style=3D"width: auto; max-width: 110px;" alt=3D"A warning." src=3D"https:/=
/make.wordpress.org/plugins/files/2025/07/manual-review-warning-1.png"></td=
>
        <td>
            Throughout the entire process, and before you reply, please ens=
ure that:
            <ul>
                <li>You have <strong>addressed all reported issues and thor=
oughly tested your plugin</strong>. Make sure your updated version does not=
 introduce new issues, such as fatal errors during activation.</li>
                <li>You are replying to <strong>this email thread</strong>.=
</li>
                <li>Your reply is <strong>brief and to the point</strong>. =
Include only information that is relevant for the next review; there is no =
need to describe every change you made, and please avoid unnecessary verbos=
ity or AI-generated filler.</li>
                <li>If you wish to alter your permalink (aka the plugin slu=
g)  "adoology-for-woocommerce", you have <strong>explicitly stated your des=
ired permalink</strong> in your reply. Changing the display name alone is n=
ot sufficient, and <u>permalinks cannot be altered after approval</u>.</li>
                <li>You are making <strong>meaningful progress between revi=
ew rounds</strong>. Updates that resolve only a small portion of the report=
ed issues delay the review process, and the plugin may be rejected out of r=
espect for the volunteers' time and other plugin authors waiting in the que=
ue. Plugins rejected under these circumstances will not be reviewed again.<=
/li>
                <li>You treat the review as a learning opportunity and impr=
ove your <strong>knowledge of development practices and the directory guide=
lines</strong> accordingly.</li>
            </ul>
            Each volunteer may review hundreds of plugins every week. Follo=
wing these indications and submitting a well-tested update makes the review=
 process smoother for everyone.
        </td>
    </tr>
    </tbody>
</table>
<br>
<h3>Response times</h3>
<br>
Reviews are handled by volunteers alongside their own personal and job comm=
itments, so <u>response times depend entirely on their availability</u>. Yo=
u may receive a reply within a few days, after a week or two, or occasional=
ly after a longer period. We appreciate your patience and understanding.
<br><br>
Please avoid requesting status updates unless you have been waiting an unus=
ually long time (a month or more). Status requests do not accelerate the re=
view; they slow it down by taking time away from the volunteers working thr=
ough the queue. If you have replied to <u>this email thread</u>, you are in=
 the queue. If you believe a reply went missing, check your spam folder and=
 other inboxes first; if you have lost this email, write to us from the sam=
e address we sent it to and request that it be resent.
<br><br>
<h3>Guidelines</h3>
<br>
In addition to code quality, security and functionality, all plugins must a=
dhere to the guidelines you accepted when submitting this plugin. Please ke=
ep them in mind when making changes: <a href=3D"https://developer.wordpress=
.org/plugins/wordpress-org/detailed-plugin-guidelines/">https://developer.w=
ordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/</a>.
<br><br>
Please note that failure to follow the Plugins Directory guidelines is take=
n seriously and may result in the loss of your WordPress.org plugin hosting=
 privileges.
<br>

<br><br><h2>Have you read the guidelines and this plugin complies with them=
?</h2>
<br>
Upon submitting your plugin, you agreed and confirmed that it complies with=
 the <a href=3D"https://developer.wordpress.org/plugins/wordpress-org/detai=
led-plugin-guidelines/">WordPress.org Plugin Directory Guidelines</a>, whic=
h apply to all plugins in the directory.
<br><br>
Our automated tools have detected patterns that may require a closer look r=
egarding compliance with certain guidelines. We will verify this during our=
 manual review, but it=E2=80=99s best to address any potential issues befor=
ehand. In particular, please pay attention to the following:
<ul><li>Plugins <strong>should not hijack the admin dashboard</strong>. Upg=
rade prompts, notices, alerts, and the like must be limited in scope and us=
ed with moderation. <i>(Guideline 11)</i></li></ul>
<br>
Please check it, and if you think everything is fine, do not worry. Our too=
ls are very thorough and may highlight different things as potential issues=
.<br><br><h2>Are you the rightful owner of this plugin?</h2>
<br>
We know this might seem like an odd question =E2=80=94 especially coming fr=
om an algorithm =E2=80=94 but bear with us; it=E2=80=99ll all make sense so=
on.
<br><br>
Here's how it works: If your plugin uses a name or URL associated with a sp=
ecific entity, we need to confirm that you actually represent that entity. =
It's that simple.
<br><br>
So, how do we verify your identity? In most cases, we rely on the domain in=
 your email address.
<br><br>
This situation usually comes up if you're using a personal account to submi=
t a plugin meant to represent a company or if you=E2=80=99re a third party =
attempting to upload a plugin on someone else=E2=80=99s behalf (please don=
=E2=80=99t do that).
<br><br>
This is what we know <strong>about this plugin</strong>:
<ul><li>Plugin name: <strong>"Adoology for WooCommerce"</strong> in the <co=
de>readme.txt</code> file</li><li>Plugin name: <strong>"Adoology for WooCom=
merce"</strong> in the <code>adoology-connector.php</code> file</li><li>Slu=
g: adoology-for-woocommerce</li><li>Author: Adoology</li><li>Plugin URI: <a=
 href=3D"https://adoology.com" target=3D"_blank">https://adoology.com</a></=
li><li>Contributors:</li><ul><li>adoology</li></ul></ul>
<br>
This is what we know <strong>about you</strong>:
<ul><li>Username: <strong>amadulhaque8</strong><ul><li>=F0=9F=9F=A7 Your us=
ername does not match any of the contributors declared in the plugin.</li><=
/ul></li><li>Email: <strong><a href=3D"mailto:mdamadulhaque8@gmail.com">mda=
madulhaque8@gmail.com</a></strong><ul><li>=F0=9F=9F=A5 Your email domain "g=
mail.com" does not seem to be related to any of the URLs, names, trademarks=
 and/or or services declared in the plugin.</li><li>=F0=9F=9F=A5 A gmail.co=
m account cannot be used as a valid form of identification towards clarifyi=
ng ownership, even if it contains your or your company's name. Anyone can c=
reate any gmail.com account that is not already in use. You could be you, b=
ut you could also be anyone else.</li></ul></li></ul>
<br>
Please note that we carry out these checks to protect people like you. If s=
omeone else submitted a plugin under your name or your company's name, you =
would rightly be upset if we failed to confirm ownership in a demonstrably =
reliable way.<br><br>
<br>
<h3>=E2=98=91=EF=B8=8F You can demonstrate or clarify ownership in <strong>=
one</strong> of the following ways:</h3>
<ul>
    <li>=F0=9F=8C=90 <strong>Perform a DNS check on the owner's domain</str=
ong>. Add a TXT record at the owner's domain root <code>@</code> with the f=
ollowing value: <code>wordpressorg-amadulhaque8-verification</code><br>Befo=
re replying, verify that the record is already visible at the relevant doma=
in, for example: <a href=3D"https://toolbox.googleapps.com/apps/dig/#TXT/ad=
oology.com">adoology.com</a> (DNS changes can take some time to propagate).
        <br>
        <i>Note: This will only be considered valid if that's done in the o=
wner's domain and we can relate your account as being controlled by the own=
er. If you are a third party please follow other verification methods.</i>
        <br><br>
    </li>
    <li>=F0=9F=93=A9 <strong>Update your WordPress.org email address</stron=
g> to one under the domain of the entity associated with the plugin. You ca=
n change it in <a href=3D"https://profiles.wordpress.org/amadulhaque8/profi=
le/edit/group/3/?screen=3Dhome">Your WordPress.org profile</a>, we cannot c=
hange it for you.
        <br>
        <i>Note: We will continue the review in this email thread. Any new =
emails will be sent to the new email account.</i>
        <br><br>
    </li>
    <li>=F0=9F=91=A4 <strong>Reply asking us to transfer this submission to=
 the correct WordPress.org account.</strong> Please, tell us the username. =
If the owner does not yet have an account, they can create it using an emai=
l that is under the domain of the entity associated to the plugin.
        <br>
        =E2=9A=A0=EF=B8=8F <i><strong>Do not resubmit this plugin using the=
 new account!</strong>. I will transfer it for you. Simply reply to this em=
ail with the new username.</i>
        <br>
        <i>Note: Once the plugin has been approved, you can ask the owner t=
o add you as a committer in the "Advanced" section of the plugin, so that y=
ou can commit code using your account.</i>
        <br><br>
    </li>
    <li>=F0=9F=9B=A0 <strong>Change the plugin's display name and slug</str=
ong> to make it clear that the plugin is not officially affiliated with any=
 other entity. Upload an updated version of your plugin via the "<a href=3D=
"https://wordpress.org/plugins/developers/add/">Add your plugin</a>" page. =
Ask us to change the slug.<br><br></li>
    <li>Reply to this email <strong>clarifying the situation</strong>, we k=
now that sometimes you might be using an email account that belongs to the =
same entity or an entity that owns the other entity. Also, if you already h=
ave established plugins in the directory under the same account we can use =
that as tacit verification.</li>
</ul>
<br>
Remember that, if you own other plugins, all the plugins belonging to the s=
ame entity should be under the same WordPress.org account.
<br><br>
=E2=9A=A0=EF=B8=8F Please do not resubmit this plugin using a different acc=
ount. Both submissions will be rejected if you do so, and your accounts may=
 be suspended until the situation is resolved. Instead, ask us to change th=
e owner =E2=80=94 we can certainly do that.
<br><br><h2>Have you checked for common technical issues?</h2>
<br>
Please ensure that your plugin adheres to the guidelines and best practices=
, including the following:
<br><br>
<h3>=F0=9F=94=B4 Use wp_enqueue commands</h3>
<br>
<strong>=E2=84=B9=EF=B8=8F Why it matters</strong>: Because of performance =
and compatibility, please make use of the built in functions for including =
static and dynamic JS and/or CSS.
<br><br>
<strong>=F0=9F=94=8D Identify JS and CSS outputs</strong>: Look for any <co=
de>&lt;script&gt;</code> or <code>&lt;style&gt;</code> HTML tags in your pl=
ugin. In the majority of cases you could enqueue them.
<br><br>
<strong>=F0=9F=9B=A0 Fix it</strong>: Make use of the specific function for=
 enqueue them:
<br>
<table>
=09<thead>
=09<tr>
=09=09<th>Type of code</th>
=09=09<th>Functions</th>
=09</tr>
=09</thead>
=09<tbody>
=09<tr>
=09=09<td>Static JS</td>
=09=09<td><code>wp_register_script()</code>, <code>wp_enqueue_script()</cod=
e>, <code>admin_enqueue_scripts()</code></td>
=09</tr>
=09<tr>
=09=09<td>Inline JS</td>
=09=09<td><code>wp_add_inline_script()</code></td>
=09</tr>
=09<tr>
=09=09<td>Static CSS</td>
=09=09<td><code>wp_register_style()</code>, <code>wp_enqueue_style()</code>=
</td>
=09</tr>
=09<tr>
=09=09<td>Inline CSS</td>
=09=09<td><code>wp_add_inline_style()</code></td>
=09</tr>
=09</tbody>
</table>
<br>
=F0=9F=91=89 In the public pages you can enqueue them using the hook <code>=
wp_enqueue_scripts()</code>.<br>
=F0=9F=91=89 In the admin pages you can enqueue them using the hook  <code>=
admin_enqueue_scripts()</code>. You can also use <code>admin_print_scripts(=
)</code> and <code>admin_print_styles()</code>.<br>
=F0=9F=91=89 As of WordPress 6.3, you can <a href=3D"https://make.wordpress=
.org/core/2023/07/14/registering-scripts-with-async-and-defer-attributes-in=
-wordpress-6-3/">easily pass attributes like defer or async</a>, as of Word=
Press 5.7, you can <a href=3D"https://make.wordpress.org/core/2021/02/23/in=
troducing-script-attributes-related-functions-in-wordpress-5-7/">pass other=
 attributes by using functions and filters</a>.
<br><br>
Example:<br>
<pre><code>function adoofowo_enqueue_script() {
    wp_enqueue_script( 'adoofowo_js', plugins_url( 'inc/main.js', __FILE__ =
), array(), ADOOFOWO_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'adoofowo_enqueue_script' );</code></pre>
Your JS/CSS is now <strong>enqueued</strong>!
<br><br>
Possible cases from your plugin include:
<br><pre><code>src/OrderForm.php:431 &lt;style&gt;<br>src/Settings.php:708 =
 ...?&gt;&lt;style&gt;.adoology-cards{display:grid;grid-template-columns:re=
peat(auto-fit,minmax(190px,1fr));gap:16px;max-width:1100px;margin:20px 0}.a=
doology-card{padding:18px;border:1px solid #dcdcde;border-radiu... </code><=
/pre><br><br>
<h3>=F0=9F=94=B4 Nonces and User Permissions Before Processing Requests</h3=
>
<br>
<strong>=E2=84=B9=EF=B8=8F Why it matters</strong>: Nonces and permissions =
checks ensure that the request comes from a trusted source, protecting agai=
nst security threats like CSRF (Cross-Site Request Forgery) attacks.
<br>
<i>Please <a href=3D"https://developer.wordpress.org/apis/security/nonces/"=
>check the official WordPress docs on nonces</a> and <a href=3D"https://dev=
eloper.wordpress.org/news/2023/08/understand-and-use-wordpress-nonces-prope=
rly/#when-to-use-nonces">this article</a> for details =E2=80=94 this is a q=
uick summary.</i>
<br><br>
<strong>=F0=9F=94=8D Spot it</strong>: Look for functions that interact wit=
h <code>$_GET</code>, <code>$_POST</code>, <code>$_REQUEST</code> and perfo=
rm actions triggered by the user/browser that modify data or perform sensit=
ive actions (e.g., privileged actions or data manipulation). For such actio=
ns, you should always check for a nonce.
<br>
Additionally, verify user permissions if the action is restricted to certai=
n roles (e.g., admin, editor).
<br>
When in doubt, play it safe: always check.
<br><br>
<strong>=F0=9F=9B=A0 Fix it</strong>
<br>
<ol>
    <li>Create a nonce ( <code>wp_nonce_field()</code>, <code>wp_nonce_url(=
)</code>, <code>wp_create_nonce()</code> ). If you need to pass the nonce t=
o JavaScript you can make use of <code>wp_localize_script()</code></li>
    <li>Pass it with the request.</li>
    <li>Check the nonce ( <code>check_admin_referer()</code>, <code>check_a=
jax_referer()</code>, <code>wp_verify_nonce()</code> ) , and permissions if=
 applicable ( <code>current_user_can()</code> ) .</li>
</ol>
<br>
Example (nonce check):<br>
<pre><code>
function adoofowo_save_email(){
    if ( !isset( $_POST['adoofowo_nonce'] ) || !wp_verify_nonce( sanitize_t=
ext_field( wp_unslash( $_POST['adoofowo_nonce'] ) ), 'adoofowo_save_email_a=
ction' ) ) {
        wp_send_json_error( 'Invalid nonce.' );
    }

    if ( !current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Insufficient permissions.' );
    }

    if ( isset( $_POST['post_id'] ) &amp;&amp; isset( $_POST['price'] ) ) {
        update_post_meta( absint( $_POST['post_id'] ), 'price', floatval( $=
_POST['price'] ) );
    }
}
add_action( 'wp_ajax_adoofowo_save_email', 'adoofowo_save_email' );
</code></pre>
=E2=9A=A0=EF=B8=8F Without nonce and permissions checks, anyone could call =
this AJAX endpoint and change the price data for any post - risky!
<br><br><br><h2>Other details</h2>
<br>
We've detected some other details that you may want to check.<br><br><h3>=
=F0=9F=9F=A1 Requires Plugins: found possible dependencies</h3><br>
The "Requires Plugins" header is a comma-separated list of WordPress.org-fo=
rmatted slugs for its dependencies.
<br><br>
Does your plugin extend another plugin available in the WordPress.org direc=
tory? If so, you can use the "Requires Plugins" header to have WordPress ch=
eck if that plugin is installed and activated before your plugin is activat=
ed.
<br><br>
You can check out the documentation at: <a href=3D"https://make.wordpress.o=
rg/core/2024/03/05/introducing-plugin-dependencies-in-wordpress-6-5/">https=
://make.wordpress.org/core/2024/03/05/introducing-plugin-dependencies-in-wo=
rdpress-6-5/</a>
<br><br>
We have found that your plugin probably extends another plugin, so using th=
is header may be useful for you, please check it out.
<br><br>
Analysis result:
<br>By checking your plugin slug "adoology-for-woocommerce" we found coinci=
dences with "woocommerce"<br>You can easily solve this by adding or updatin=
g the "Requires Plugins" header at "adoology-connector.php", like so:<pre><=
code>Requires Plugins: woocommerce</code></pre><br><br><br><h3>=F0=9F=94=B4=
=EF=B8=8F Tested Up To Value is Out of Date, Invalid, or Missing</h3><br>
The tested up to value in your plugin is not set to the current version of =
WordPress. This means your plugin will not show up in searches, as we requi=
re plugins to be compatible and documented as tested up to the most recent =
version of WordPress.
<br><br>
Please update your readme to show that it is tested up to the most recent <=
strong>stable major</strong> version of WordPress. For example if WordPress=
 said that version 99.3.2 was the current release, you would be able to set=
 your value to 99.3 as that's the major release.
<br><br>
The following links will assist you in understanding WordPress's versioning=
 and the latest version:
<br><br>
<a href=3D"https://wordpress.org/download/">https://wordpress.org/download/=
</a>
<br>
<a href=3D"https://make.wordpress.org/core/handbook/about/release-cycle/ver=
sion-numbering/">https://make.wordpress.org/core/handbook/about/release-cyc=
le/version-numbering/</a>
<br><br>
You cannot set it beyond the current version, as that will cause your plugi=
n not to be available on searches. Also remember that WordPress has made la=
te changes in releases, so claiming compatibility with an incomplete versio=
n is likely to land you in trouble.
<br><br>
From your readme file:
<br>ERROR: Tested up to: "6.8" that version is bellow currently accepted Wo=
rdPress versions (It's expected to be at least "7.0").<br><br><h3>=F0=9F=94=
=B4=EF=B8=8F You haven't added yourself to the "Contributors" list for this=
 plugin.</h3><br>
In your readme file, the "Contributors" parameter is a case-sensitive, comm=
a-separated list of all WordPress.org usernames that have contributed to th=
e code.
<br><br>
Your username is not in this list, you need to add yourself if you want to =
appear listed as a contributor to this plugin.
<br><br>
If you don't want to appear that's fine, this is not mandatory, we're infor=
ming you just in case.
<br><br>
Analysis result:
<br><br># WARNING: None of the listed contributors "adoology" is the WordPr=
ess.org username of the owner of the plugin "amadulhaque8".<br><br><br><h3>=
=F0=9F=94=B4=EF=B8=8F Using composer but could not find composer.json file<=
/h3><br>
We noticed that your plugin is using Composer to handle library dependencie=
s, that's great as it will help maintaining and updating your plugin in the=
 future while avoiding collisions with other plugins that are using same li=
braries.
<br><br>
The <code>composer.json</code> file describes the dependencies of your proj=
ect and may contain other metadata as well. It's a small file that typicall=
y can be found in the top-most directory of your plugin.
<br><br>
As one of the strengths of open source is the ability to review, observe, a=
nd adapt code, <strong>we would like to ask you to include that file in you=
r plugin</strong>, even if it is only used for development purposes. This w=
ill allow others to exercise the open source freedoms from which we all ben=
efit.
<br><br>
From your plugin:
<br>
<pre><code>composer.json file not found in "adoology-connector/composer.jso=
n"</code></pre><br><br><br><h3>=F0=9F=94=B4=EF=B8=8F Undocumented use of a =
3rd Party / external service</h3><br>
Plugins are permitted to require the <strong>use of third party/external se=
rvices</strong> as long as they are <strong>clearly documented</strong>.
<br><br>
When your plugin reach out to external services, you must disclose it. This=
 is true even if you are the one providing that service.
<br><br>
You are required to document it in a clear and plain language, so users are=
 aware of: <strong>what data is sent, why, where and under which conditions=
</strong>.
<br><br>
To do this, you must update your readme file to  <strong>clearly explain th=
at your plugin relies on third party/external services</strong>, and includ=
e <strong>at least the following information</strong> for each third party/=
external service that this plugin uses:
<ul>
    <li>What the service is and what it is used for.</li>
    <li>What data is sent and when.</li>
    <li>Provide links to the service's terms of service and privacy policy.=
</li>
</ul>
Remember, this is for your own legal protection. Use of services must be up=
front and well documented. This allows users to ensure that any legal issue=
s with data transmissions are covered.
<br><br>
Example:
<pre>=3D=3D External services =3D=3D<br>
This plugin connects to an API to obtain weather information, it's needed t=
o show the weather information and forecasts in the included widget.<br>
It sends the user's location every time the widget is loaded (If the locati=
on isn't available and/or the user hasn't given their consent, it displays =
a configurable default location).
This service is provided by "PRT Weather INC": <a href=3D"https://developer=
.wordpress.org/plugins/wordpress-org/how-to-use-subversion/">terms of use</=
a>, <a href=3D"https://developer.wordpress.org/plugins/wordpress-org/plugin=
-assets/">privacy policy</a>.
</pre>
<br>
=F0=9F=94=97 Please verify that the terms and privacy links exist and they =
have the proper content. <strong>We will check those links</strong> in the =
next review.
<br><br>
Example(s) from your plugin:
<br><pre><code>src/Webhooks.php:349 wp_safe_remote_request($webhook-&gt;get=
_delivery_url(), ['method' =3D&gt; 'POST', 'timeout' =3D&gt; 20, 'redirecti=
on' =3D&gt; 0, 'reject_unsafe_urls' =3D&gt; true, 'httpversion' =3D&gt; '1.=
1', 'blocking' =3D&gt; true, 'user-agent' =3D&gt; sprintf('WooCommerce/%s H=
ookshot (Adoology retry)', defined('WC_VERSION') ? WC_VERSION : ''), 'body'=
 =3D&gt; $body, 'headers' =3D&gt; ['Content-Type' =3D&gt; 'application/json=
', 'X-WC-Webhook-Source' =3D&gt; home_url('/'), 'X-WC-Webhook-Topic' =3D&gt=
; $topic, 'X-WC-Webhook-Resource' =3D&gt; $topic_bits[0] ?? 'product', 'X-W=
C-Webhook-Event' =3D&gt; $topic_bits[1] ?? 'updated', 'X-WC-Webhook-Signatu=
re' =3D&gt; base64_encode(hash_hmac('sha256', $body, wp_specialchars_decode=
($secret, ENT_QUOTES), true)), 'X-WC-Webhook-ID' =3D&gt; $webhook_id, 'X-WC=
-Webhook-Delivery-ID' =3D&gt; $webhook-&gt;get_new_delivery_id(), 'X-Adoolo=
gy-Event-ID' =3D&gt; $event_id]]);<br># =E2=9C=A8 The plugin sends webhook =
retry requests to Adoology, but the readme lacks a Terms of Service or Priv=
acy Policy link for Adoology.</code></pre><br><pre><code>src/Settings.php:3=
57 ...'adoology_api_base_url', 'https://api.adoology.com')); ?&gt;" require=
d /&gt;&lt;/td&gt;...<br>src/Settings.php:128 return (string) Options::get(=
'adoology_api_base_url', 'https://api.adoology.com');<br>src/Settings.php:7=
8 'default' =3D&gt; 'https://api.adoology.com',<br>src/Options.php:64 'adoo=
logy_api_base_url' =3D&gt; 'https://api.adoology.com',<br>src/ApiClient.php=
:26 $url =3D self::validate_base_url((string) Options::get('adoology_api_ba=
se_url', 'https://api.adoology.com'));<br>src/Settings.php:130 $current =3D=
 (string) Options::get('adoology_api_base_url', 'https://api.adoology.com')=
;</code></pre><br><br><br><h3>=F0=9F=94=B4=EF=B8=8F Sanitization for regist=
er_setting()</h3><br>
Fields registered through <code>register_setting()</code> should be sanitiz=
ed properly.
<br><br>
Fortunately, in this case, it is very easy to indicate which function shoul=
d be used to sanitize the input. =F0=9F=A7=BC
<br><br>
The third argument of this function accepts an <code>array()</code> in whic=
h you can add your sanitizing function in the <code>sanitize_callback</code=
> key.
<br><br>
Just like this:
<br>
<pre><code>register_setting(
    'adoofowo_group',
    'adoofowo_option_name',
    array(
        'type'              =3D&gt; 'string',
        'sanitize_callback' =3D&gt; 'sanitize_text_field',
    )
);</code></pre>
<br>
Make sure you use a proper sanitization function (<a href=3D"https://develo=
per.wordpress.org/apis/security/sanitizing/">WordPress has plenty of them!<=
/a>) and add other options as needed.
<br><br>
Please, <a href=3D"https://developer.wordpress.org/reference/functions/regi=
ster_setting/">check out the <code>register_setting()</code> documentation<=
/a> for more information and code examples.
<br><br>
Example(s) from your plugin:
<br>
<pre><code>src/Settings.php:109 register_setting('adoology_features', $opti=
on, ['type' =3D&gt; 'integer', 'sanitize_callback' =3D&gt; [$this, $sanitiz=
er]]);</code></pre><br><br><br><h3>=F0=9F=94=B4=EF=B8=8F Internationalizati=
on: Text domain does not match plugin slug.</h3><br>
In order to make a string translatable in your plugin you are using a set o=
f special functions. These functions collectively are known as "gettext".
<br><br>
These functions have <a href=3D"https://developer.wordpress.org/plugins/int=
ernationalization/how-to-internationalize-your-plugin/#text-domains">a para=
meter called "text domain"</a>, which is a unique identifier for retrieving=
 translated strings.
<br><br>
This "text domain" must be the same as your plugin slug so that the plugin =
can be translated by the community using the tools provided by the director=
y.
As for example, if this plugin slug is "adoology-for-woocommerce" the Inter=
nationalization functions should look like:
<br>
<code>esc_html__( 'Hello', 'adoology-for-woocommerce' );</code>
<br><br>
From your plugin, you have set your text domain as follows:
<br><pre><code># This plugin is using the domain "adoology-connector" for 2=
56 element(s).</code></pre><br>However, the current plugin slug is this:<pr=
e><code>adoology-for-woocommerce</code></pre><br><br>
<br><br>
<h2>=F0=9F=91=89 Your next steps</h2>
<br>
This is <strong>your checklist</strong>:
<ol><li>Have you read the guidelines and this plugin complies with them?</l=
i><li>Are you the rightful owner of this plugin?</li><li>Have you checked f=
or common technical issues?</li><li>Other details</li></ol>
<br>
If there is something that needs to be fixed, please:
<ol>
    <li>Take your time and <strong>fix</strong> it. Make sure that everythi=
ng was addressed and the plugin works.</li>
    <li><strong>Update</strong> your plugin files at the "<a href=3D"https:=
//wordpress.org/plugins/developers/add/">Add your plugin</a>" page, while b=
eing logged in with your account  "amadulhaque8".</li>
    <li><strong>Reply</strong> to <u>this</u> email.
        <br><i>Please <strong>keep your reply short, direct and clear</stro=
ng>. Avoid overly verbose and long AI responses. Do not list the changes ma=
de, we don't need that, we will review the entire plugin again, we won't co=
mpare the changes. However, please share any important context or clarifica=
tions that may help us during the review.</i>
    </li>
</ol>
<br>
If after checking the list and do the changes you feel that everything is r=
ight or need further clarification, please reply to this email and a volunt=
eer will help you.
<br><br>
If you believe there is a requirement you cannot accomplish and choose not =
to make changes, your plugin submission will be rejected after three months=
.
<br><br><h2>Thanks!</h2>
<br>
By taking these steps, you're helping the Plugin Review Team work more effi=
ciently =E2=80=94 meaning your plugin (along with the thousands of others i=
n the queue) can be reviewed faster. =F0=9F=9A=80 We really appreciate your=
 contribution!
<br><br><h2>Disclaimers</h2>
<br>
If, at any time during the review process, you wish to change your permalin=
k (aka the plugin slug) "adoology-for-woocommerce", you <strong>must explic=
itly and clearly tell us what you would like it to be</strong>. Just changi=
ng it in your code and in the display name is not sufficient. Remember, per=
malinks cannot be altered after approval.
<br>
This email was partially auto-generated, so please be aware that some infor=
mation might not be entirely accurate. No personal data was shared with the=
 AI during this process. If you notice any obvious errors or something seem=
s off, feel free to reply =E2=80=94 we=E2=80=99ll be happy to take a closer=
 look and readjust this automation.
<br><br>
Review ID: AUTOPREREVIEW =E2=9D=97OWN adoology-for-woocommerce/amadulhaque8=
/21Sep26/T1 21Sep26/4.3 (P0TDX373201HGN)</div><br>
        <br><div id=3D"helpscout-signature">--<br>WordPress Plugins Team |=
=C2=A0plugins@wordpress.org<br>https://make.wordpress.org/plugins/ <br>http=
s://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guideline=
s/<br>https://wordpress.org/plugins/plugin-check/</div>
                            </div>
        <span height=3D"0" style=3D"font-size: 0px; height:0px; line-height=
: 0px; color:#ffffff;" id=3D"hsContentRef">{#HS:3457661406-1124381#}</span>
        <img src=3D"https://secure.helpscout.net/notification/convo/read/34=
57661406/10557854393/" alt=3D""/>        <br>
</div>

<div class=3D"gmail_quote" style=3D"font-family:sans-serif;">
           =20
</div>
</body>
</html>


--=_149b49720702d36b8c90baac34f0984f--
