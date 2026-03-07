import { SidebarProvider, SidebarTrigger, SidebarInset } from "@/components/ui/sidebar"
import { AppSidebar } from "@/components/app-sidebar"
import { Separator } from "@/components/ui/separator"
import React from "react"

export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <SidebarProvider className="sidebar-accent-brand">
      <AppSidebar />
      <SidebarInset>
        <header className="flex h-16 shrink-0 items-center gap-2 border-b px-4 bg-white">
          <SidebarTrigger className="-ml-1" />
          {/* <Separator orientation="vertical" className="mr-2 h-4" /> */}
            {/* We can add breadcrumbs here later */}
        </header>
        <div className="flex flex-1 flex-col gap-4 p-4 pt-0 mt-4">
          {children}
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
