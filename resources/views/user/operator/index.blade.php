<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
      @fluxAppearance
  </head>
  <body class="min-h-screen bg-white dark:bg-zinc-800 antialiased">
      <flux:sidebar sticky collapsible="mobile" class="bg-zinc-50 dark:bg-zinc-900 border-r border-zinc-200 dark:border-zinc-700">
          <flux:sidebar.header>
              <flux:sidebar.brand
                  href="#"
                  logo="{{ Vite::asset('resources/images/logo.png') }}"
                  size="lg"
                  name="SmartICCT"
              />
              <flux:sidebar.collapse class="lg:hidden" />
          </flux:sidebar.header>

          {{-- <flux:sidebar.search placeholder="Search..." /> --}}

          <flux:sidebar.nav>

              <flux:sidebar.item icon="home" href="#" current>Dashboard</flux:sidebar.item>

              <flux:sidebar.item icon="inbox" badge="12" href="#">Inbox</flux:sidebar.item>

              <flux:sidebar.group expandable heading="Routes" class="grid">
                  <flux:sidebar.item href="#">Local</flux:sidebar.item>
                  <flux:sidebar.item href="#">Provincial</flux:sidebar.item>
              </flux:sidebar.group>

              <flux:sidebar.group expandable heading="Queueing" class="grid">
                  <flux:sidebar.item href="#">Bus</flux:sidebar.item>
                  <flux:sidebar.item href="#">Jeep</flux:sidebar.item>
                  <flux:sidebar.item href="#">Van</flux:sidebar.item>
              </flux:sidebar.group>

              <flux:sidebar.item icon="document-text" href="#">Travel Records</flux:sidebar.item>

              <flux:sidebar.item icon="document-text" href="#">Reports</flux:sidebar.item>

              <flux:sidebar.item icon="document-text" href="#">Your Card</flux:sidebar.item>


          </flux:sidebar.nav>

          <flux:sidebar.spacer />

          <flux:dropdown position="top" align="start" class="max-lg:hidden">
              <flux:sidebar.profile avatar="https://fluxui.dev/img/demo/user.png" name="Olivia Martin" />
              <flux:menu>
                  <flux:menu.radio.group>
                    <flux:sidebar.item icon="cog-6-tooth" href="#">Settings</flux:sidebar.item>
                    <flux:sidebar.item icon="information-circle" href="#">Help</flux:sidebar.item>
                  </flux:menu.radio.group>
                  <flux:menu.separator />
                  <flux:menu.item icon="arrow-right-start-on-rectangle">Logout</flux:menu.item>
              </flux:menu>
          </flux:dropdown>
      </flux:sidebar>

      {{-- Mobile header --}}
      <flux:header class="lg:hidden">
          <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
          <flux:spacer />
          <flux:dropdown position="top" alignt="start">
              <flux:profile avatar="https://fluxui.dev/img/demo/user.png" />
              <flux:menu>
                  <flux:menu.radio.group>
                    <flux:sidebar.item icon="cog-6-tooth" href="#">Settings</flux:sidebar.item>
                    <flux:sidebar.item icon="information-circle" href="#">Help</flux:sidebar.item>
                  </flux:menu.radio.group>
                  <flux:menu.separator />
                  
                    <form action="/logout/{{ auth()->user()->role }}" method="post">
                      <flux:menu.item
                          as="button"
                          type="submit"
                          icon="arrow-right-start-on-rectangle"
                          class="w-full cursor-pointer"
                          data-test="logout-button"
                      >
                          Logouts
                      </flux:menu.item>
                    </form>
                  
              </flux:menu>
          </flux:dropdown>
      </flux:header>

      <flux:main>

          {{-- {{ $slot }} --}}

      </flux:main>

      @fluxScripts

  </body>
</html>